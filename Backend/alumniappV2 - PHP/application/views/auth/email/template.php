<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title><?php echo $subject_title; ?></title>
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
                            <a href="#">
                                <img src="<?php echo site_url('assets/img/logo.png'); ?>" alt="Alumni Portal" height="44" style="display:inline-block;vertical-align:middle;">
                            </a>
                            <p style="margin:14px 0 0;color:rgba(255,255,255,0.85);font-size:13px;letter-spacing:0.5px;text-transform:uppercase;font-weight:500;">Alumni Portal</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 40px 32px;">

                            <h1 style="margin:0 0 24px;font-size:22px;font-weight:700;color:#0077cc;"><?php echo $subject_title; ?></h1>

                            <p style="margin:0 0 16px;font-size:15px;color:#374151;">Hi <strong><?php echo $subject_name; ?></strong>,</p>

                            <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;"><?php echo $msg_body; ?></p>

                            <!-- Divider -->
    						<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

                           

                            <p style="margin:0;font-size:15px;color:#374151;line-height:1.7;">Warm Regards,<br><strong>FGGC Alumni Portal</strong></p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#005fa3;border-top:1px solid #e5e7eb;padding:24px 40px;text-align:center;">
                            <p style="margin:0 0 4px;font-size:13px;color:#9ca3af;">This email was sent by FGGC Alumni Portal. Please do not reply to this email.</p>
                            <p style="margin:0;font-size:12px;color:#f0f4f8;">&copy; <?php echo date('Y'); ?> FGGC Alumni Portal. All rights reserved.</p>
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
