<?php
/**
 * Email Helper - Send emails using company SMTP configuration
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Send email using company's SMTP configuration
 * 
 * @param int $company_id Company ID to get SMTP config
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body
 * @param string $to_name Optional recipient name
 * @return bool Success status
 */
function sendCompanyEmail($company_id, $to, $subject, $body, $to_name = '')
{
    try {
        $db = getDB();

        // Get company SMTP configuration
        $stmt = $db->prepare("
            SELECT 
                smtp_host,
                smtp_port,
                smtp_username,
                AES_DECRYPT(smtp_password, 'prisma_smtp_key_2024') as smtp_password,
                smtp_from_email,
                smtp_from_name,
                smtp_encryption,
                smtp_enabled
            FROM companies 
            WHERE id = ?
        ");
        $stmt->execute([$company_id]);
        $config = $stmt->fetch();

        if (!$config || !$config['smtp_enabled']) {
            error_log("SMTP not configured or disabled for company {$company_id}");
            return false;
        }

        // Create PHPMailer instance
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $config['smtp_port'];
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom($config['smtp_from_email'], $config['smtp_from_name']);
        $mail->addAddress($to, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();

        // Log successful send
        error_log("Email sent to {$to}: {$subject}");

        return true;

    } catch (Exception $e) {
        error_log("Email send failed: {$mail->ErrorInfo}");
        return false;
    } catch (\Exception $e) {
        error_log("Email error: {$e->getMessage()}");
        return false;
    }
}

/**
 * Generate HTML email template
 */
function getEmailTemplate($title, $content, $footer = '')
{
    return "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Arial, sans-serif; background-color: #f5f5f5;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f5f5f5; padding: 40px 20px;'>
            <tr>
                <td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background-color: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #00C9B7, #0099A8); padding: 30px; text-align: center;'>
                                <h1 style='margin: 0; color: white; font-size: 28px; font-weight: 700;'>Prisma</h1>
                                <p style='margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;'>{$title}</p>
                            </td>
                        </tr>
                        <!-- Content -->
                        <tr>
                            <td style='padding: 40px 40px 30px 40px; color: #2c3e50; line-height: 1.6;'>
                                {$content}
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style='padding: 20px 40px 30px 40px; border-top: 1px solid #e5e7eb;'>
                                <p style='margin: 0; color: #64748b; font-size: 13px; line-height: 1.6;'>
                                    {$footer}
                                </p>
                                <p style='margin: 12px 0 0 0; color: #94a3b8; font-size: 12px;'>
                                    Este es un mensaje automático, por favor no respondas a este email.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";
}

/**
 * Send notification when request is approved
 */
function sendRequestApprovedEmail($request_id)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT r.*, a.name as app_name, a.company_id
        FROM requests r
        INNER JOIN apps a ON r.app_id = a.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request || !$request['requester_email']) {
        return false;
    }

    $content = "
        <p style='font-size: 16px; margin: 0 0 20px 0;'>Hola <strong>{$request['requester_name']}</strong>,</p>
        
        <p style='margin: 0 0 20px 0;'>¡Buenas noticias! Tu solicitud de mejora ha sido <strong style='color: #00C9B7;'>aprobada</strong> y la hemos añadido a nuestra lista de desarrollo.</p>
        
        <div style='background: #f8fafc; border-left: 4px solid #00C9B7; padding: 20px; margin: 25px 0; border-radius: 4px;'>
            <h3 style='margin: 0 0 12px 0; font-size: 16px; color: #2c3e50;'>{$request['title']}</h3>
            <p style='margin: 0 0 8px 0; color: #64748b; font-size: 14px;'><strong>Aplicación:</strong> {$request['app_name']}</p>
            <p style='margin: 0; color: #64748b; font-size: 14px;'>{$request['description']}</p>
        </div>
        
        <p style='margin: 0 0 20px 0;'>Te mantendremos informado cuando empecemos a trabajar en ella y cuando esté lista para usar.</p>
        
        <p style='margin: 0; color: #64748b;'>Gracias por ayudarnos a mejorar,<br><strong>El equipo de Prisma</strong></p>
    ";

    $footer = "Si tienes alguna pregunta sobre esta mejora, no dudes en contactarnos.";

    return sendCompanyEmail(
        $request['company_id'],
        $request['requester_email'],
        "✅ Tu mejora ha sido aprobada - {$request['title']}",
        getEmailTemplate('Solicitud Aprobada', $content, $footer),
        $request['requester_name']
    );
}

/**
 * Send notification when request status changes to in_progress
 */
function sendRequestInProgressEmail($request_id)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT r.*, a.name as app_name, a.company_id
        FROM requests r
        INNER JOIN apps a ON r.app_id = a.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request || !$request['requester_email']) {
        return false;
    }

    $content = "
        <p style='font-size: 16px; margin: 0 0 20px 0;'>Hola <strong>{$request['requester_name']}</strong>,</p>
        
        <p style='margin: 0 0 20px 0;'>Te escribimos para informarte que hemos empezado a trabajar en tu mejora:</p>
        
        <div style='background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 20px; margin: 25px 0; border-radius: 4px;'>
            <h3 style='margin: 0 0 8px 0; font-size: 16px; color: #2c3e50;'>{$request['title']}</h3>
            <p style='margin: 0; color: #64748b; font-size: 14px;'><strong>Aplicación:</strong> {$request['app_name']}</p>
        </div>
        
        <p style='margin: 0 0 20px 0;'>Estamos trabajando en implementarla. Te avisaremos cuando esté lista para usar.</p>
        
        <p style='margin: 0; color: #64748b;'>Saludos,<br><strong>El equipo de Prisma</strong></p>
    ";

    $footer = "Pronto podrás disfrutar de esta mejora en tu aplicación.";

    return sendCompanyEmail(
        $request['company_id'],
        $request['requester_email'],
        "🚀 Estamos trabajando en tu mejora - {$request['title']}",
        getEmailTemplate('En Desarrollo', $content, $footer),
        $request['requester_name']
    );
}

/**
 * Send notification when request is completed
 */
function sendRequestCompletedEmail($request_id)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT r.*, a.name as app_name, a.company_id
        FROM requests r
        INNER JOIN apps a ON r.app_id = a.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request || !$request['requester_email']) {
        return false;
    }

    $content = "
        <p style='font-size: 16px; margin: 0 0 20px 0;'>Hola <strong>{$request['requester_name']}</strong>,</p>
        
        <p style='margin: 0 0 20px 0;'>¡Tenemos excelentes noticias! La mejora que solicitaste ya está <strong style='color: #10b981;'>completada y disponible</strong>:</p>
        
        <div style='background: #f0fdf4; border-left: 4px solid #10b981; padding: 20px; margin: 25px 0; border-radius: 4px;'>
            <h3 style='margin: 0 0 8px 0; font-size: 16px; color: #2c3e50;'>{$request['title']}</h3>
            <p style='margin: 0; color: #64748b; font-size: 14px;'><strong>Aplicación:</strong> {$request['app_name']}</p>
        </div>
        
        <p style='margin: 0 0 20px 0;'>Ya puedes empezar a usar esta nueva funcionalidad. Esperamos que te sea de utilidad.</p>
        
        <p style='margin: 0; color: #64748b;'>Gracias por tu sugerencia,<br><strong>El equipo de Prisma</strong></p>
    ";

    $footer = "¿Tienes otra idea de mejora? No dudes en enviárnosla.";

    return sendCompanyEmail(
        $request['company_id'],
        $request['requester_email'],
        "🎉 Tu mejora está lista - {$request['title']}",
        getEmailTemplate('Mejora Completada', $content, $footer),
        $request['requester_name']
    );
}

/**
 * Send notification when request is rejected
 */
function sendRequestRejectedEmail($request_id)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT r.*, a.name as app_name, a.company_id
        FROM requests r
        INNER JOIN apps a ON r.app_id = a.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request || !$request['requester_email']) {
        return false;
    }

    $content = "
        <p style='font-size: 16px; margin: 0 0 20px 0;'>Hola <strong>{$request['requester_name']}</strong>,</p>
        
        <p style='margin: 0 0 20px 0;'>Gracias por enviarnos tu solicitud de mejora. Tras revisarla, hemos decidido no proceder con su implementación en este momento:</p>
        
        <div style='background: #fef2f2; border-left: 4px solid #ef4444; padding: 20px; margin: 25px 0; border-radius: 4px;'>
            <h3 style='margin: 0 0 8px 0; font-size: 16px; color: #2c3e50;'>{$request['title']}</h3>
            <p style='margin: 0; color: #64748b; font-size: 14px;'><strong>Aplicación:</strong> {$request['app_name']}</p>
        </div>
        
        <p style='margin: 0 0 20px 0;'>Esto puede deberse a varias razones: prioridades actuales del proyecto, viabilidad técnica, o porque ya estamos trabajando en una solución similar.</p>
        
        <p style='margin: 0 0 20px 0;'>Valoramos mucho tu feedback y te animamos a seguir compartiendo tus ideas con nosotros.</p>
        
        <p style='margin: 0; color: #64748b;'>Saludos,<br><strong>El equipo de Prisma</strong></p>
    ";

    $footer = "Seguimos abiertos a tus sugerencias para mejorar nuestros productos.";

    return sendCompanyEmail(
        $request['company_id'],
        $request['requester_email'],
        "Actualización sobre tu solicitud - {$request['title']}",
        getEmailTemplate('Solicitud Revisada', $content, $footer),
        $request['requester_name']
    );
}
