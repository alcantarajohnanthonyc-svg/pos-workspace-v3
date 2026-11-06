<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * ALL the emails of POS Workspace in ONE place. To change a subject or a message, edit it here: nothing else.
 *
 * HOW A TEMPLATE WORKS
 *   subject  the email subject (one line)
 *   heading  the big title inside the email
 *   body     the message. One line = one paragraph. {placeholders} are filled by the system.
 *            A line whose placeholder has no value is left out (so "Note: {note}" only shows when there is a note).
 *   button   the text of the button that opens the page (the link comes from the notification)
 *
 * KEYS      event            e.g. approval_request
 *           event.audience   a different wording for one kind of reader, e.g. approval_approved.requester
 *           If "event.audience" does not exist, "event" is used.
 *
 * PLACEHOLDERS  {recipient} {requester} {actor} {verb} {target} {action_label} {reference} {note} {summary}
 *               {module} {title} {body} {url} {datetime}
 *               ({target} already comes with quotes, like “UR CATALUNAN GRANDE”.)
 *
 * The in-app text (the bell) is NOT here: it is written in the code that sends the notification.
 */
class EmailTemplates extends BaseConfig
{
    /** The frame around every email. */
    public array $layout = [
        'brand'  => 'POS Workspace V3',
        'color'  => '#059669',
        'button' => 'Open in POS Workspace',
        'footer' => 'You receive this email because of the notification settings of this module. Please do not reply to this email.',
    ];

    public array $templates = [

        // ---- approvals -------------------------------------------------------------------------------------------
        'approval_request' => [
            'subject' => 'Approval needed: {action_label}',
            'heading' => 'Approval needed',
            'body'    => "Hi {recipient},\n{requester} asked to {verb} {target}.\nReference: {reference}\nPlease review the request and approve or reject it.",
            'button'  => 'Review request',
        ],
        'approval_request.final' => [
            'subject' => 'Final approval needed: {action_label}',
            'heading' => 'Final approval needed',
            'body'    => "Hi {recipient},\n{actor} approved the first step of {requester}’s request to {verb} {target}.\nReference: {reference}\nIt now waits for your final decision.",
            'button'  => 'Review request',
        ],
        'approval_approved.requester' => [
            'subject' => 'Approved: {action_label}',
            'heading' => 'Your request was approved',
            'body'    => "Hi {recipient},\n{actor} approved your request to {verb} {target}.\nReference: {reference}\nNote: {note}",
            'button'  => 'View request',
        ],
        'approval_approved.export' => [
            'subject' => 'Your export is ready to download',
            'heading' => 'Your export was approved',
            'body'    => "Hi {recipient},\n{actor} approved your export request.\nReference: {reference}\nThe download link works once and for 24 hours.",
            'button'  => 'Download the file',
        ],
        'approval_approved.approver' => [
            'subject' => 'Request approved: {action_label}',
            'heading' => 'Request approved',
            'body'    => "Hi {recipient},\n{actor} approved {requester}’s request to {verb} {target}.\nReference: {reference}\nNote: {note}",
            'button'  => 'View request',
        ],
        'approval_rejected.requester' => [
            'subject' => 'Rejected: {action_label}',
            'heading' => 'Your request was rejected',
            'body'    => "Hi {recipient},\n{actor} rejected your request to {verb} {target}.\nReason: {note}\nReference: {reference}",
            'button'  => 'View request',
        ],
        'approval_rejected.approver' => [
            'subject' => 'Request rejected: {action_label}',
            'heading' => 'Request rejected',
            'body'    => "Hi {recipient},\n{actor} rejected {requester}’s request to {verb} {target}.\nReason: {note}\nReference: {reference}",
            'button'  => 'View request',
        ],

        // ---- other events ----------------------------------------------------------------------------------------
        'record_changed' => [
            'subject' => '{title}',
            'heading' => '{title}',
            'body'    => "Hi {recipient},\n{body}",
            'button'  => 'Open',
        ],
        'import_completed' => [
            'subject' => 'Import finished',
            'heading' => 'Your import finished',
            'body'    => "Hi {recipient},\n{body}",
            'button'  => 'Open',
        ],
        'report_generated' => [
            'subject' => 'Your report is ready',
            'heading' => 'Report ready',
            'body'    => "Hi {recipient},\n{body}",
            'button'  => 'Open the report',
        ],
        'system_alert' => [
            'subject' => '{title}',
            'heading' => '{title}',
            'body'    => "Hi {recipient},\n{body}",
            'button'  => 'Open',
        ],

        // ---- sent by "Send a test email" ---------------------------------------------------------------------------
        'test' => [
            'subject' => 'POS Workspace test email',
            'heading' => 'The email setup works',
            'body'    => "Hi {recipient},\nThis is a test email sent on {datetime}.\nIf you can read this, POS Workspace can send emails.",
            'button'  => 'Open POS Workspace',
        ],
    ];
}