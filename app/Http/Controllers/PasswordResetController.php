<?php

namespace App\Http\Controllers;

use App\Mail\CodigoRecuperacionMail;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const MINUTOS_EXPIRACION = 10;
    private const SEGUNDOS_COOLDOWN_REENVIO = 60;
    private const MAX_INTENTOS = 5;

    /**
     * Paso 1: el usuario ingresa su correo y se le envía el código de 6 dígitos.
     */
    public function enviarCodigo(Request $request)
    {
        $datos = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower($datos['email']);

        // Cooldown: evita reenvíos en ráfaga.
        $ultimo = DB::table('password_reset_codes')
            ->where('email', $email)
            ->orderByDesc('created_at')
            ->first();

        if ($ultimo) {
            // created_at viene como string plano de DB::table(); hay que
            // parsearlo a Carbon explícitamente. Además, en Carbon 3
            // diffInSeconds() ya no redondea ni fuerza el valor absoluto
            // por defecto, así que hay que pedirlo explícitamente (true)
            // y castear a int para no mostrar decimales ni negativos.
            $segundosTranscurridos = (int) Carbon::parse($ultimo->created_at)->diffInSeconds(now(), true);

            if ($segundosTranscurridos < self::SEGUNDOS_COOLDOWN_REENVIO) {
                $restante = self::SEGUNDOS_COOLDOWN_REENVIO - $segundosTranscurridos;
                return response()->json([
                    'mensaje' => "Espera {$restante} segundos antes de solicitar otro código.",
                ], 429);
            }
        }

        // Mensaje genérico: no revela si el correo existe o no en la BD.
        // Las cuentas de Google/Facebook no tienen contraseña local que
        // restablecer (ver constraint chk_usuario_auth), así que solo se
        // envía código si es una cuenta local con contraseña.
        $usuario = Usuario::where('correo', $email)->first();

        if ($usuario && $usuario->proveedor === 'local') {
            $codigo = (string) random_int(100000, 999999);

            DB::table('password_reset_codes')->where('email', $email)->delete();

            DB::table('password_reset_codes')->insert([
                'email' => $email,
                'codigo' => Hash::make($codigo),
                'token_verificacion' => null,
                'intentos' => 0,
                'expira_en' => now()->addMinutes(self::MINUTOS_EXPIRACION),
                'verificado_en' => null,
                'created_at' => now(),
            ]);

            Mail::to($email)->send(new CodigoRecuperacionMail($codigo));
        }

        return response()->json([
            'mensaje' => 'Si el correo está registrado, te enviamos un código de verificación.',
        ]);
    }

    /**
     * Paso 2: el usuario ingresa el código de 6 dígitos recibido.
     * Si es correcto, se genera un token temporal que autoriza el paso 3
     * (así no se vuelve a exponer el código de 6 dígitos).
     */
    public function verificarCodigo(Request $request)
    {
        $datos = $request->validate([
            'email' => 'required|email',
            'codigo' => 'required|digits:6',
        ]);

        $email = strtolower($datos['email']);

        $registro = DB::table('password_reset_codes')->where('email', $email)->first();

        if (!$registro) {
            return response()->json(['mensaje' => 'Solicita un nuevo código.'], 404);
        }

        if (now()->greaterThan($registro->expira_en)) {
            return response()->json(['mensaje' => 'El código expiró. Solicita uno nuevo.'], 410);
        }

        if ($registro->intentos >= self::MAX_INTENTOS) {
            return response()->json(['mensaje' => 'Superaste el número de intentos. Solicita un nuevo código.'], 429);
        }

        if (!Hash::check($datos['codigo'], $registro->codigo)) {
            DB::table('password_reset_codes')->where('email', $email)->increment('intentos');
            $restantes = self::MAX_INTENTOS - ($registro->intentos + 1);
            return response()->json(['mensaje' => "Código incorrecto. Te quedan {$restantes} intentos."], 422);
        }

        $tokenVerificacion = Str::random(48);

        DB::table('password_reset_codes')->where('email', $email)->update([
            'token_verificacion' => Hash::make($tokenVerificacion),
            'verificado_en' => now(),
        ]);

        return response()->json([
            'mensaje' => 'Código verificado correctamente.',
            'token' => $tokenVerificacion,
        ]);
    }

    /**
     * Paso 3: el usuario define su nueva contraseña, autorizado por el
     * token temporal obtenido en el paso 2.
     */
    public function restablecer(Request $request)
    {
        $datos = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $email = strtolower($datos['email']);

        $registro = DB::table('password_reset_codes')->where('email', $email)->first();

        if (!$registro || !$registro->token_verificacion || !$registro->verificado_en) {
            return response()->json(['mensaje' => 'Debes verificar tu código primero.'], 403);
        }

        if (now()->diffInMinutes($registro->verificado_en) > self::MINUTOS_EXPIRACION) {
            return response()->json(['mensaje' => 'El proceso expiró. Empieza de nuevo.'], 410);
        }

        if (!Hash::check($datos['token'], $registro->token_verificacion)) {
            return response()->json(['mensaje' => 'Token inválido.'], 403);
        }

        Usuario::where('correo', $email)->update([
            'contrasena' => Hash::make($datos['password']),
        ]);

        // Uso único: se elimina el registro para que no se pueda reutilizar.
        DB::table('password_reset_codes')->where('email', $email)->delete();

        return response()->json(['mensaje' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
    }
}
