<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Email (SMTP) settings of POS Workspace - kept in this Config file, no .env needed.
 * Fill in the SMTP lines below, then (as Super Admin) open  /notifications/email-test  to send a test email.
 *
 * GMAIL / GOOGLE WORKSPACE:  SMTPHost smtp.gmail.com, port 587, crypto tls, user = the full email address,
 *   password = an "App password" (Google account > Security > 2-Step Verification > App passwords). Not the normal password.
 * Do not share this file after you put the real password in it.
 */
class Email extends BaseConfig
{
    public string $fromEmail  = 'posworkspace@bounty.com.ph';                   // e.g. posworkspace@bounty.com.ph
    public string $fromName   = 'POS Workspace';
    public string $recipients = '';

    public string $userAgent = 'CodeIgniter';
    public string $protocol  = 'smtp';                // mail | sendmail | smtp
    public string $mailPath  = '/usr/sbin/sendmail';

    public string $SMTPHost      = 'smtp.gmail.com';                // e.g. smtp.gmail.com
    public string $SMTPUser      = 'jcalcantara@bounty.com.ph';                // e.g. posworkspace@bounty.com.ph
    public string $SMTPPass      = 'isgehrgxglsjnjbp';                // the App password
    public int $SMTPPort         = 587;
    public int $SMTPTimeout      = 10;                // seconds: a slow mail server must not freeze Save / Approve
    public bool $SMTPKeepAlive   = false;
    public string $SMTPCrypto    = 'tls';             // tls (port 587) | ssl (port 465)

    public bool $wordWrap  = true;
    public int $wrapChars  = 76;
    public string $mailType = 'html';
    public string $charset  = 'UTF-8';
    public bool $validate   = true;
    public int $priority    = 3;
    public string $CRLF     = "\r\n";
    public string $newline  = "\r\n";
    public bool $BCCBatchMode = false;
    public int $BCCBatchSize  = 200;
    public bool $DSN          = false;
}