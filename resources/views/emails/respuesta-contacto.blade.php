<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
</head>
<body style="margin:0; padding:0; background:#f7f7f8; font-family:Arial, Helvetica, sans-serif; color:#222;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f8; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #ececec;">

          <tr>
            <td style="background:#1a9ecf; padding:22px 28px;">
              <p style="margin:0; font-size:20px; font-weight:800; color:#111111; line-height:1.2;">
                Ro<span style="color:#ffffff;">om</span>match
              </p>
              <p style="margin:4px 0 0; font-size:12.5px; color:#e4f4fb;">
                Donde tu comodidad es nuestra prioridad
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:26px 28px 8px;">
              <p style="margin:0 0 4px; font-size:12px; font-weight:bold; color:#1a9ecf; text-transform:none;">
                Respuesta a tu mensaje
              </p>
              <p style="margin:0; font-size:13px; color:#888;">
                ¡Hola {{ $datos['nombre'] }}! Nuestro equipo de soporte respondió tu solicitud.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 28px 4px;">
              <div style="background:#eaf6fc; border-radius:10px; padding:16px 18px; font-size:14px; line-height:1.65; color:#1c1c1c; white-space:pre-line;">{{ $datos['respuesta'] }}</div>
              <p style="margin:10px 0 0; font-size:12.5px; color:#666;">
                — {{ $datos['atendido_por'] }}, equipo de soporte de RoomMatch
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 28px 4px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:8px 0; font-size:13px; color:#666; width:90px;">Asunto</td>
                  <td style="padding:8px 0; font-size:13px; color:#111; font-weight:bold;">{{ $asuntoLabel }}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:8px 28px 26px;">
              <p style="margin:14px 0 6px; font-size:12px; font-weight:bold; color:#333;">Tu mensaje original</p>
              <div style="background:#f7f7f8; border-radius:10px; padding:14px 16px; font-size:13.5px; line-height:1.6; color:#333; white-space:pre-line;">{{ $datos['mensaje'] }}</div>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 28px; border-top:1px solid #ececec;">
              <p style="margin:0; font-size:11px; color:#aaa;">
                Si necesitas algo más, vuelve a escribirnos desde el formulario de Contáctanos en RoomMatch.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
