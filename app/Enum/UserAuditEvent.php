<?php

namespace Gazelle\Enum;

/**
 * These are the allowed values for user_audit_trail.event
 */

enum UserAuditEvent: string {
    case activity   = 'activity';
    case historical = 'historical'; // previously users_info.AdminComment
    case invite     = 'invite';
    case staffNote  = 'staff-note';
    case mfa        = 'mfa';
    case ratio      = 'ratio';
    case userclass  = 'userclass';
    case warning    = 'warning';
}
