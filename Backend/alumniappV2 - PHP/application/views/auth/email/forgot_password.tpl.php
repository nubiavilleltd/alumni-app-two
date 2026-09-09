<?php
$reset_url = 'https://alumni-app-two.vercel.app/auth/reset-password/' . $forgotten_password_code;
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Reset Your Password – Alumni Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:'Inter',Arial,sans-serif;">

    <!-- Wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f4f8;padding:40px 16px;">
        <tr>
            <td align="center">

                <!-- Card -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(16,24,40,0.08);">

                    <!-- Header Banner -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#0077cc 0%,#005fa3 100%);padding:36px 40px;text-align:center;">
                            <img src="<?php echo site_url('assets/img/logo.png'); ?>" alt="Alumni Portal" height="44" style="display:inline-block;vertical-align:middle;">
                            <p style="margin:14px 0 0;color:rgba(255,255,255,0.85);font-size:13px;letter-spacing:0.5px;text-transform:uppercase;font-weight:500;">Alumni Portal</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 40px 32px;">

                            <!-- Icon -->
                            <div style="text-align:center;margin-bottom:24px;">
                                <div style="display:inline-block;background:#dbeafe;border-radius:50%;width:64px;height:64px;line-height:64px;text-align:center;font-size:28px;">
                                    🔐
                                </div>
                            </div>

                            <h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#111827;text-align:center;">Reset Your Password</h1>
                            <p style="margin:0 0 28px;font-size:14px;color:#6b7280;text-align:center;">We received a request to reset your account password.</p>

                            <p style="margin:0 0 8px;font-size:15px;color:#374151;">Hi <strong><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></strong>,</p>

                            <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">
                                Someone requested a password reset for your Alumni Portal account. If this was you, click the button below to choose a new password. The link is valid for <strong>24 hours</strong>.
                            </p>

                            <!-- CTA Button -->
                            <div style="text-align:center;margin-bottom:28px;">
                                <a href="<?php echo $reset_url; ?>"
                                   style="display:inline-block;background:linear-gradient(135deg,#0077cc,#005fa3);color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 36px;border-radius:10px;letter-spacing:0.3px;box-shadow:0 4px 14px rgba(0,119,204,0.35);">
                                    Reset Password
                                </a>
                            </div>

                            <!-- Divider -->
                            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 24px;">

                            <!-- Fallback link -->
                            <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">Button not working? Copy and paste this link into your browser:</p>
                            <p style="margin:0 0 28px;word-break:break-all;">
                                <a href="<?php echo $reset_url; ?>"
                                   style="font-size:13px;color:#0077cc;text-decoration:underline;">
                                    <?php echo $reset_url; ?>
                                </a>
                            </p>

                            <!-- Warning box -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;margin-bottom:8px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#92400e;">&#9888;&#65039; Things to know</p>
                                        <ul style="margin:0;padding-left:18px;font-size:13px;color:#78350f;line-height:1.8;">
                                            <li>This reset link expires in <strong>24 hours</strong>.</li>
                                            <li>If you didn't request this, you can safely ignore this email — your password will not change.</li>
                                            <li>Never share this link with anyone.</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:24px 40px;text-align:center;">
                            <p style="margin:0 0 4px;font-size:13px;color:#9ca3af;">This email was sent by Alumni Portal. Please do not reply to this email.</p>
                            <p style="margin:0;font-size:12px;color:#d1d5db;">&copy; <?php echo date('Y'); ?> Alumni Portal. All rights reserved.</p>
                        </td>
                    </tr>

                </table>
                <!-- /Card -->

            </td>
        </tr>
    </table>
    <!-- /Wrapper -->

</body>
</html>
