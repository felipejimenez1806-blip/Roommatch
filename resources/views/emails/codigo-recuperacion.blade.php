<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Código de recuperación</title>
</head>
<body style="margin:0; padding:0; background:#f1f4f8; font-family: 'Segoe UI', Roboto, Arial, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding: 32px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="420" cellpadding="0" cellspacing="0"
               style="background:#ffffff; border-radius:16px; padding:32px; box-shadow:0 10px 30px rgba(15,30,60,0.08);">
          <tr>
            <td style="text-align:center; padding-bottom: 12px;">
              <h1 style="font-size:1.3rem; color:#1f2733; margin:0;">Roommatch</h1>
            </td>
          </tr>
          <tr>
            <td style="color:#1f2733; font-size:0.95rem; line-height:1.5;">
              <p>Hola,</p>
              <p>Recibimos una solicitud para restablecer tu contraseña. Usa el siguiente código para continuar. Es válido por <strong>10 minutos</strong>:</p>
            </td>
          </tr>
          <tr>
            <td style="text-align:center; padding: 20px 0;">
              <span style="display:inline-block; font-size:2rem; font-weight:700; letter-spacing:10px; color:#1857b8; background:#f1f6ff; padding:14px 20px; border-radius:12px;">
                {{ $codigo }}
              </span>
            </td>
          </tr>
          <tr>
            <td style="color:#6b7684; font-size:0.82rem; line-height:1.5;">
              <p>Si no solicitaste este código, puedes ignorar este correo — tu contraseña seguirá siendo la misma.</p>
              <p>— Soporte Roommatch</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
