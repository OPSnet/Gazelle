<?php

declare(strict_types=1);

namespace Gazelle\Search;

use Gazelle\Enum\UserStatus;
use Gazelle\Util\SortableTableHeader;

class Users extends \Gazelle\Base {
    protected array $cte    = [];
    protected array $join   = [];
    protected array $cond   = [];
    protected array $args   = [];
    protected array $column = [];
    protected string $totalSql;
    protected string $pageSql;
    protected SortableTableHeader $heading;

    // Note regarding the joining of tables. The $join array is associative:
    // e.g. $join['userclass'] = 'relay.permissions'
    // This allows different methods to set the table independently and not
    // have it be joined twice (or more).

    public function __construct(public readonly array $input) {
        $this->init($input);
    }

    /**
     * Table aliases
     * =============
     * atu  cte returning matching staff notes for the user
     * ish  cte returning the most recent site ip by user
     * ith  cte returning the most recent tracker ip by user
     * psc  cte returning the secondary userclasses for the user
     * mfa  multi_factor_auth
     * p    relay.permissions
     * ru   relay.referral_users
     * s    relay.stylesheets
     * ua   relay.user_attribute
     * ub   relay.user_bonus
     * uf   relay.user_flt
     * ul   relay.users_levels
     * uha  relay.user_has_attribute
     * uinv relay.users_main (the row of the inviter)
     * uhis relay.user_has_invite_source
     * uis  relay.invite_source
     * ula  relay.user_last_access
     * uls  relay.users_leech_stats
     * um   relay.users_main (the row of the user)
     * usf  relay.user_summary_fltoken
     * usm  relay.user_summary_misc
     * usr  relay.user_summary_request
     * uss  relay.user_summary_seed
     * ust  relay.user_summary_tgroup
     * w    user_warning
     */

    /**
     * Fields from other tables needed in the condition or result.
     */
    public function joinMap(string $field): string {
        return match ($field) {
            'bptotal'         => 'inner join relay.user_bonus ub on (ub.user_id = um."ID")',
            'extprofile'      => 'left join user_external_profile uep on (uep.id_user = um."ID")',
            'fltoken'         => 'inner join relay.user_flt uf on (uf.user_id = um."ID")',
            'inviter'         => 'left join relay.users_main uinv on (uinv."ID" = um.inviter_user_id)', // left join: user might not have an inviter
            'invsource'       => 'left join relay.user_has_invite_source uhis on (uhis.user_id = um."ID") left join relay.invite_source uis using (invite_source_id)',
            'lastseen'        => 'left join relay.user_last_access ula on (ula.user_id = um."ID")', // left join: unconfirmed might not have logged in yet
            'leech'           => 'inner join relay.users_leech_stats uls on (uls."UserID" = um."ID")',
            'mfa'             => 'left join multi_factor_auth mfa on (mfa.id_user = um."ID")',
            'referral'        => 'left join relay.referral_users ru on (ru."UserID" = um."ID")',
            'siteip'          => 'inner join ish on (ish.id_user = um."ID")',
            'staffnote'       => 'inner join atu on (atu.id_user = um."ID")',
            'stylesheet'      => 'inner join relay.stylesheets s on (s."ID" = um.stylesheet_id)',
            'summary-fltoken' => 'inner join user_summary_fltoken usf on (usf.id_user = um."ID")',
            'summary-misc'    => 'inner join user_summary_misc usm on (usm.id_user = um."ID")',
            'summary-request' => 'inner join user_summary_request usr on (usr.id_user = um."ID")',
            'summary-seed'    => 'inner join user_summary_seed uss on (uss.id_user = um."ID")',
            'summary-tgroup'  => 'inner join user_summary_tgroup ust on (ust.id_user = um."ID")',
            'trackerip'       => 'inner join ith on (ith.id_user = um."ID")',
            'userclass'       => 'inner join relay.permissions p on (p."ID" = um."PermissionID")',
            'warned'          => 'inner join user_warning w on (w.id_user = um."ID")',
            default           => "join:$field", // error
        };
    }

    /**
     * Map a field name to a table column
     */
    public function columnMap(string $field): string {
        return  match ($field) {
            'attr'            => 'uha."UserAttrID"',
            'bprate'          => 'uss.bp_accrual_hour',
            'bptotal'         => 'ub.points',
            'disable'         => '(select array_agg(ua."Name") from relay.user_attr ua inner join relay.user_has_attr uha on (uha."UserAttrID" = ua."ID") where uha."UserID" = um."ID")',
            'downloadgb'      => 'uls."Downloaded"',
            'email-hist'      => 'he.email',         // for historical email addresses
            'email'           => 'um."Email"',
            'enabled'         => 'um."Enabled"',
            'extprofile'      => 'uep.profile',
            'flrx'            => 'usf.received',
            'fltoken'         => 'uf.tokens',
            'fltx'            => 'usf.gifted',
            'flused'          => 'usf.used',
            'invited'         => 'usm.invited',
            'inviter-id'      => 'uinv."ID"',        // for template |user_url
            'inviter'         => 'uinv."Username"',  // for searching (given a name, who did they invite?)
            'invsource'       => 'uhis.invite_source_id',
            'invsource-view'  => 'uis.name',
            'joined'          => 'um.created',
            'lastseen'        => 'ula.last_access',
            'leech'           => 'uss.leeching',
            'mfa'             => 'mfa.id_user',
            'note'            => 'note',             // pass-thru for audit_trail_user
            'note_ts'         => 'note_ts',          // pass-thru for audit_trail_user
            'referral'        => 'ru."Site"',
            'referral-uid'    => 'ru."Username"',
            'requestcreate'   => 'usr.created_total',
            'requestgaingb'   => 'usr.bounty_size',
            'requestvotegb'   => 'usr.vote_size',
            'secclass'        => 'ul."PermissionID"',
            'secclass-view'   => '(select string_agg(psc.badge, \', \' order by psc.badge) from relay.users_levels ulsc inner join relay.permissions psc on (psc."ID" = ulsc."PermissionID") where ulsc."UserID" = um."ID" and psc.badge != \'\')',
            'seedhour'        => 'uss.seedtime_hour',
            'seeding'         => 'uss.seeding',
            'siteip'          => 'ish.ip',
            'siteip-view'     => '(select ip from ip_site_history ish where ish.id_user = um."ID" order by upper(seen) desc limit 1)',
            'snatch-uniq'     => 'uss.snatch_unique',
            'snatch'          => 'uss.snatch_total',
            'staffnote'       => 'atu.note_list',
            'stylesheet-view' => 's."Name"',
            'stylesheet'      => 'um.stylesheet_id',
            'tdownload'       => 'ust.download_total',
            'tdownload-uniq'  => 'ust.download_unique',
            'trackerip'       => 'ith.ip',
            'trackerip-hist'  => 'ith."IP"', // goddamned historical inconsistency
            'uploadgb'        => 'uls."Uploaded"',
            'uploads'         => 'ust.upload',
            'userclass-view'  => 'p."Name"',
            'userclass'       => 'p."ID"',
            'userid'          => 'um."ID"',          // pass-thru unchanged
            'username'        => 'um."Username"',
            'warned-view'     => 'upper(w.warning)', // the date the warning ends
            'warned'          => 'w.warning',
            default           => "column?:$field",   // error
        };
    }

    /**
     * Column headings displayed in the results
     */
    public function headingMap(string $field): string {
        return match ($field) {
            'attr'           => 'Attribute',
            'bprate'         => 'BP/hour',
            'bptotal'        => 'BP total',
            'enabled'        => 'Enabled',
            'extprofile'     => 'External profile',
            'disable'        => 'Revoked?',
            'downloadgb'     => 'Down (GiB)',
            'flrx'           => 'FL received',
            'fltoken'        => 'FL tokens',
            'fltx'           => 'FL gifted',
            'flused'         => 'FL used',
            'email'          => 'Email',
            'invsource'      => 'Recruit site',
            'invited'        => '# Invited',
            'inviter'        => 'Invited by',
            'joined'         => 'Joined',
            'lastseen'       => 'Last seen',
            'leech'          => 'Leeching',
            'locked'         => 'Locked',
            'mfa'            => 'MFA',
            'referral'       => 'Referral site',
            'requestgaingb'  => 'Bounty (GiB)',
            'requestvotegb'  => 'Voted (GiB)',
            'requestcreate'  => '# Requests',
            'secclass'       => 'Secondary',
            'seeding'        => 'Seeding',
            'seedhour'       => 'Seedtime (h)',
            'siteip'         => 'Site IP',
            'snatch'         => 'Snatched',
            'snatch-uniq'    => 'Snatched (Uniq)',
            'staffnote'      => 'Staff note',
            'stylesheet'     => 'Stylesheet',
            'tdownload'      => 'Torrents DLed',
            'tdownload-uniq' => 'Torrents DLed (Uniq)',
            'trackerip'      => 'Tracker IP',
            'uploadgb'       => 'Up (GiB)',
            'uploads'        => '# Uploads',
            'userid'         => 'User ID',
            'userclass'      => 'Userclass',
            'username'       => 'Username',
            default          => "head?:$field",
        };
    }

    /**
     * Most columns are sorted ascending, a smaller number are descending
     */
    public function naturalOrder(string $field): string {
        return match ($field) {
            'bprate',
            'bptotal',
            'downloadgb',
            'flrx',
            'fltoken',
            'fltx',
            'flused',
            'invited',
            'joined',
            'lastseen',
            'leech',
            'requestgaingb',
            'requestvotegb',
            'requestcreate',
            'seedhour',
            'seeding',
            'siteip',
            'snatch',
            'snatch-uniq',
            'tdownload',
            'tdownload-uniq',
            'trackerip',
            'uploadgb',
            'uploads' => 'desc',
            default   => 'asc',
        };
    }

    public function init(array $input): void {
        // process the filter arguments
        if (isset($input['bprate-min']) || isset($input['bprate-max'])) {
            $this->setBprate(
                (float)($input['bprate-min'] ?? 0.0),
                (float)($input['bprate-max'] ?? 0.0),
            );
        }
        if (isset($input['disable'], $input['disable-op'])) {
            $this->setDisabled(
                $input['disable'],
                $input['disable-op'],
            );
        }
        if (isset($input['email'], $input['email-op'])) {
            $this->setEmail(
                $input['email'],
                $input['email-op'],
                isset($input['email-hist']),
                isset($input['email-not']),
            );
        }
        if (isset($input['enabled'])) {
            $this->setEnabled($input['enabled']);
        }
        if (isset($input['extprofile'], $input['extprofile-op'])) {
            $this->setBasic('extprofile', $input['extprofile'], $input['extprofile-op']);
        }
        if (isset($input['invsource'])) {
            $this->setBasic('invsource', $input['invsource'], 'equal');
        }
        if (isset($input['inviter'], $input['inviter-op'])) {
            $this->setBasic('inviter', $input['inviter'], $input['inviter-op']);
        }
        if (isset($input['joined-from']) || isset($input['joined-to'])) {
            $from = null;
            if (isset($input['joined-from'])) {
                try {
                    $from = new \DateTimeImmutable($input['joined-from']);
                } catch (\DateMalformedStringException) {
                    ; // fine, leave it as null
                }
            }
            $to = null;
            if (isset($input['joined-to'])) {
                try {
                    $to = new \DateTimeImmutable($input['joined-to']);
                } catch (\DateMalformedStringException) {
                    ; // fine
                }
            }
            if ($from || $to) {
                $this->setJoined($from, $to);
            }
        }
        if (isset($input['mfa'])) {
            $this->setMFA($input['mfa']);
        }
        if (isset($input['referral'], $input['referral-uid'])) {
            $this->setReferral($input['referral'], trim((string)$input['referral-uid']));
        }
        if (isset($input['secclass'])) {
            $this->setSecondaryClass($input['secclass']);
        }
        if (isset($input['siteip'])) {
            $this->setSiteip($input['siteip'], $input['siteip-op']);
        }
        if (isset($input['staffnote'])) {
            $this->setStaffnote($input['staffnote'], $input['staffnote-op']);
        }
        if (isset($input['stylesheet'])) {
            $this->setBasic('stylesheet', $input['stylesheet'], 'equal');
        }
        if (isset($input['trackerip'])) {
            $this->setTrackerip($input['trackerip'], $input['trackerip-op'], $input['trackerip-hist']);
        }
        if (isset($input['userclass'])) {
            $this->setUserclass($input['userclass']);
        }
        if (isset($input['username'], $input['username-op'])) {
            $this->setBasic('username', $input['username'], $input['username-op']);
        }
        if (isset($input['warned'])) {
            $this->setWarned();
        }

        // handle the min-max filters
        foreach (
            [
                ''                => ['userid'], // users_main
                'bptotal'         => ['bptotal'],
                'fltoken'         => ['fltoken'],
                'summary-fltoken' => ['flrx', 'fltx', 'flused'],
                'summary-misc'    => ['invited'],
                'summary-request' => ['requestcreate'],
                'summary-seed'    => ['leech', 'seeding', 'seedtime', 'snatch'],
                'summary-tgroup'  => ['tdownload', 'tdownload-uniq', 'uploads'],
            ] as $table => $fieldList
        ) {
            foreach ($fieldList as $field) {
                [$min, $max] = ["{$field}-min", "{$field}-max"];
                $this->setMinMax(
                    $table,
                    $field,
                    (int)($input[$min] ?? -1),
                    (int)($input[$max] ?? -1),
                );
            }
        }
        foreach (
            [
                'leech'           => ['downloadgb', 'uploadgb'],
                'summary-request' => ['requestgaingb', 'requestvotegb'],
            ] as $table => $fieldList
        ) {
            foreach ($fieldList as $field) {
                [$min, $max] = ["{$field}-min", "{$field}-max"];
                $this->setMinMax(
                    $table,
                    $field,
                    (int)(($input[$min] ?? -1) * 1024 ** 3),
                    (int)(($input[$max] ?? -1) * 1024 ** 3),
                );
            }
        }

        // Process the result filters (the result may have to
        // display other fields that have not been filtered upon).
        // trackerip is handled directly in pageSql()
        // NB:
        // Viewing secclass (secondary userclasses) is not handled straight
        // away as it requires specialised handling (selecting a match among
        // a list, viewing a list of results) and so the joins are do not
        // resemble the usual state of affairs.
        if (!isset($input['view'])) {
            $input['view'] = 'username.joined.lastseen.inviter';
        }
        $this->column = explode('.', $input['view']);
        foreach (
            [
                'bptotal'         => ['bptotal'],
                'extprofile'      => ['extprofile'],
                'fltoken'         => ['fltoken'],
                'inviter'         => ['inviter'],
                'invsource'       => ['invsource'],
                'lastseen'        => ['lastseen'],
                'leech'           => ['downloadgb', 'uploadgb'],
                'mfa'             => ['mfa'],
                'referral'        => ['referral'],
                'stylesheet'      => ['stylesheet'],
                'summary-fltoken' => ['flrx', 'fltx', 'flused'],
                'summary-misc'    => ['invited'],
                'summary-request' => ['requestgaingb', 'requestvotegb', 'requestcreate'],
                'summary-seed'    => ['bprate', 'leech', 'seeding', 'seedhour', 'snatch'],
                'summary-tgroup'  => ['tdownload', 'tdownload-uniq', 'uploads'],
                'userclass'       => ['userclass'],
                'warned'          => ['warned'],
            ] as $table => $columnList
        ) {
            if (array_intersect($columnList, $this->column)) {
                $this->join[$table] = $this->joinMap($table);
            }
        }
    }

    public function condition(string $field, string $op): string {
        $column = $this->columnMap($field);
        $ph     = $this->pg()->placeholder(count($this->args));
        return match ($op) {
            'any'      => "$column = any($ph)",
            'contains' => "position($ph in $column) > 0",
            'prefix'   => "position($ph in $column) = 1",
            'regexp'   => "$column ~* $ph",
            'le'       => "$column <= $ph",
            'ge'       => "$column >= $ph",
            'lt'       => "$column < $ph",
            'gt'       => "$column > $ph",
            'notnull'  => "$column is not null",
            'subnet'   => "$column <<= $ph::inet",
            'webquery' => "$column @@ websearch_to_tsquery('simple', $ph)",
            default    => "$column = $ph", // equal
        };
    }

    public function setBasic(string $table, int|string $value, string $op): static {
        if ($table !== 'username') {
            $this->join[$table] = $this->joinMap($table);
        }
        $this->cond[$table] = $this->condition($table, $op);
        $this->args[] = $value;
        return $this;
    }

    public function setBprate(float $min, float $max): static {
        if ($min === 0.0 && $max === 0.0) {
            return $this;
        }
        if ($min && $max && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $minmax = [];
        if ($min > 0.0) {
            $minmax[] = $this->condition('bprate', 'ge');
            $this->args[] = $min;
        }
        if ($max > 0.0) {
            $minmax[] = $this->condition('bprate', 'le');
            $this->args[] = $max;
        }
        $this->cond['bprate'] = implode(' and ', $minmax);
        $this->join['summary-seed'] = $this->joinMap('summary-seed');
        return $this;
    }

    public function setDisabled(array $disable, string $op): static {
        if ($disable === []) {
            return $this;
        }
        if ($op === 'any') {
            $this->cond['disable'] = "exists (select 1 from relay.user_has_attr uha where uha.\"UserID\" = um.\"ID\" and {$this->condition('attr', 'any')})";
            $this->args[] = $this->pg()
                ->integerConverter()
                ->output(array_map(intval(...), $disable));
        } else {
            $exists = [];
            foreach ($disable as $d) {
                $exists[] = "exists (select 1 from relay.user_has_attr uha where uha.\"UserID\" = um.\"ID\" and {$this->condition('attr', 'equal')})";
                $this->args[] = $d;
            }
            $this->cond['disable'] = implode(' and ', $exists);
        }
        return $this;
    }

    public function setEmail(string $value, string $op, bool $historical, bool $negate): static {
        $email = [$this->condition('email', $op)];
        if ($historical) {
            $email[] = "exists (select 1 from history_email he where he.id_user = {$this->columnMap('userid')} and {$this->condition('email-hist', $op)})";
        }
        $this->cond['email'] = ($negate ? 'not ' : '')
            . '('
            . implode(' or ', $email)
            . ')';
        $this->args[] = $value;
        return $this;
    }

    public function setEnabled(string $value): static {
        $this->cond['enabled'] = $this->condition('enabled', 'equal');
        $this->args[] = match ($value) {
            'disabled'    => UserStatus::disabled->value,
            'unconfirmed' => UserStatus::unconfirmed->value,
            default       => UserStatus::enabled->value,
        };
        return $this;
    }

    public function setJoined(
        \DateTimeImmutable|null $from,
        \DateTimeImmutable|null $to,
    ): static {
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($from) {
            $this->cond['joined-from'] = $this->condition('joined', 'ge');
            $this->args[] = $from->format('Y-m-d 00:00:00');
        }
        if ($to) {
            $this->cond['joined-to'] = $this->condition('joined', 'lt');
            $this->args[] = $to->modify('+1 day')->format('Y-m-d 00:00:00');
        }
        return $this;
    }

    public function setMFA(string $value): static {
        $this->join['mfa'] = $this->joinMap('mfa');
        $this->cond['mfa'] = $value === 'yes'
            ? 'mfa.id_user is not null'
            : 'mfa.id_user is null';
        return $this;
    }

    public function setMinMax(string $table, string $field, int $min, int $max): static {
        if ($min < 0 && $max < 0) {
            return $this;
        }
        if ($min >= 0 && $max >= 0 && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $minmax = [];
        if ($min >= 0) {
            $minmax[] = $this->condition($field, 'ge');
            $this->args[] = $min;
        }
        if ($max >= 0) {
            $minmax[] = $this->condition($field, 'le');
            $this->args[] = $max;
        }
        $this->cond[$field] = implode(' and ', $minmax);
        if ($table !== '') {
            $this->join[$table] = $this->joinMap($table);
        }
        return $this;
    }

    public function setReferral(string $referral, string $uid): static {
        // must override the left join if filtering on a referral
        $this->cond['referral-musthave'] = "{$this->columnMap('referral')} is not null";
        $this->cond['referral'] = $this->condition('referral', 'equal');
        $this->join['referral'] = $this->joinMap('referral');
        $this->args[] = $referral;
        if ($uid !== '') {
            $this->cond['referral-uid'] = $this->condition('referral-uid', 'equal');
            $this->args[] = $uid;
        }
        return $this;
    }

    public function setSecondaryClass(array $secclass): static {
        $this->cond['secclass'] = trim(<<<END_SQL
            exists (select 1 from relay.users_levels ul where ul."UserID" = um."ID" and ul."PermissionID" = any({$this->pg()->placeholder(count($this->args))}))
            END_SQL
        );
        $this->args[] = $this->pg()
            ->integerConverter()
            ->output(array_map(intval(...), $secclass));
        return $this;
    }

    public function setSiteip(string $siteip, string $op): static {
        $query = 'select id_user, ip, row_number() over (partition by id_user order by upper(seen) desc) as rn from ip_site_history ish where '
            . $this->condition('siteip', $op);
        $this->cte['siteip']  = "ish(id_user, ip) as (select id_user, ip from ($query) latest where rn = 1)";
        $this->join['siteip'] = $this->joinMap('siteip');
        $this->args[] = $siteip;
        return $this;
    }

    public function setStaffnote(string $note, string $op): static {
        $query = "select id_user, array_agg(note order by created desc) from audit_trail_user where "
            . $this->condition($op === 'regexp' ? 'note' : 'note_ts', $op)
            . " group by id_user";
        $this->cte['staffnote']  = "atu (id_user, note_list) as ($query)";
        $this->join['staffnote'] = $this->joinMap('staffnote');
        $this->args[] = $note;
        return $this;
    }

    public function setTrackerip(string $trackerip, string $op, bool $hist): static {
        if ($hist) {
            $table = 'xbt_snatched';
            $date  = 'tstamp';
            $ip    = '"IP" as ip';
        } else {
            $table = 'xbt_files_users';
            $date  = 'mtime';
            $ip    = 'ip';
        }
        $query = "select uid, $ip, row_number() over (partition by uid order by $date desc) as rn from relay.$table ith where "
            . $this->condition($hist ? 'trackerip-hist' : 'trackerip', $op);
        $this->cte['trackerip']  = "ith(id_user, ip) as (select uid as id_user, ip from ($query) latest where rn = 1)";
        $this->join['trackerip'] = $this->joinMap('trackerip');
        $this->args[] = $trackerip;
        return $this;
    }

    public function setUserclass(array $userclass): static {
        $this->cond['userclass'] = $this->condition('userclass', 'any');
        $this->join['userclass'] = $this->joinMap('userclass');
        $this->args[] = $this->pg()
            ->integerConverter()
            ->output(array_map(intval(...), $userclass));
        return $this;
    }

    public function setWarned(): static {
        // since there are no placeholders to deal with, this devolves to the a trivial case
        $this->cond['warned'] = "{$this->columnMap('warned')} @> now()";
        $this->join['warned'] = $this->joinMap('warned');
        return $this;
    }

    public function cteSql(): string {
        return $this->cte
            ? ('with ' . implode(', ', $this->cte))
            : '';
    }

    public function isFiltered(): bool {
        return $this->args !== [];
    }

    public function totalKey(): string {
        return 'usearch_' . hash_hmac(
            DIGEST_ALGO,
            implode('|', [...$this->join, ...$this->cond, ...$this->args]),
            SEARCH_SALT,
        );
    }

    public function totalSql(): string {
        if (isset($this->totalSql)) {
            return $this->totalSql;
        }
        $cond  = implode(' and ', $this->cond);
        $where = $cond ? "where $cond" : '';
        $this->totalSql = $this->cteSql()
            . 'select count(*) from relay.users_main um '
            . implode(' ', $this->join) . ' '
            . $where;
        return $this->totalSql;
    }

    public function total(): int {
        $key   = $this->totalKey();
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $sql   = $this->totalSql();
            $total = (int)$this->pg()->scalarex($sql, ...$this->args);
            self::$cache->cache_value($key, $total, 180);
        }
        return $total;
    }

    public function pageSql(): string {
        if (isset($this->pageSql)) {
            return $this->pageSql;
        }
        $select = [];
        $needTrackerip       = false;
        $needOrderByUsername = false;
        foreach ($this->column as $field) {
            if ($field === 'staffnote' && !isset($this->cte['staffnote'])) {
                // Staff note column can only be displayed if it has been filtered
                continue;
            }
            // some fields need to be changed to obtain a more useful result
            $columnName = $this->columnMap(
                match ($field) {
                    'invsource'  => 'invsource-view', // name not id
                    'inviter'    => 'inviter-id',     // id not inviter username
                    'secclass'   => 'secclass-view',  // array of secondary userclasses
                    'siteip'     => 'siteip-view',
                    'stylesheet' => 'stylesheet-view',
                    'userclass'  => 'userclass-view', // userclass not id
                    'username'   => 'userid',         // id not username
                    'warned'     => 'warned-view',    // date of expiry
                    default      => $field,
                }
            );
            if ($field === 'trackerip') {
                // we cannot select the tracker ip straight away,
                // this has to be resolved in a second step, so
                // for the time being we ask for null as a pass-
                // through value. This will be replaced below by
                // the actual column identifier.
                $needTrackerip = true;
                $columnName    = 'null/*resolve*/';
            } elseif ($field === 'username') {
                // may not be one of the columns to order by, but
                // it does not cause any harm either.
                $needOrderByUsername = true;
            } elseif ($field === 'referral') {
                $columnName = "{$columnName} || ':' || {$this->columnMap('referral-uid')}";
            }
            $select[] = "$columnName as c" . count($select);
        }
        $cond       = implode(' and ', $this->cond);
        $where      = $cond ? "where $cond" : '';
        $phLimit    = $this->pg()->placeholder(count($this->args));
        $phOffset   = $this->pg()->placeholder(count($this->args) + 1);
        $columnName = $this->column[0] === 'username' ? 'username' : 'c0';
        $orderBy    = ["$columnName {$this->naturalOrder($this->column[0])}"];
        if (count($this->column) > 1) {
            $columnName = $this->column[1] === 'username' ? 'username' : 'c1';
            $orderBy[] = "$columnName {$this->naturalOrder($this->column[1])}";
        }
        if (count($this->column) > 2) {
            $columnName = $this->column[2] === 'username' ? 'username' : 'c2';
            $orderBy[] = "$columnName {$this->naturalOrder($this->column[2])}";
        }
        $orderByClause = "order by " . implode(', ', $orderBy);
        $limitClause   = " limit $phLimit offset $phOffset";
        if ($needTrackerip) {
            // Now it gets really tricky, hold on to your hat.
            //
            // If we need to display the tracker ip for users, we
            // cannot push down the ids we want to xbt_files_users,
            // mysql_fdw is not clever enough. Instead, we have to
            // first fetch what user ids will be returned, and then
            // push down that list (so that what was going to be
            // the page query itself becomes a CTE).
            //
            // In short, if the page query is PAGESQL and the columns
            // that are request are COLUMNS then the entire
            // query becomes (roughly)
            //
            // with result(COLUMNS, id_user) as (
            //     PAGESQL
            // ),
            // ith(id_user, ip) as (
            //     select distinct uid,
            //         first_value(ip) over (partition by uid order by mtime desc)
            //     from relay.xbt_files_users
            //     where uid in (select id_user from result)
            // )
            // select *, ith.ip
            // from result
            // left join ith using (id_user)
            // order by c0 asc, c1 desc, c2 asc
            //
            // apart from the added complication that ith.ip has to be
            // resolved wherever it appears. Also, the order by/limit is
            // handled in PAGESQL, however, the final select needs to
            // repeat only the order by.
            //
            // Add the id of the user in a known column, regardless of
            // whether it is to be displayed or not.
            $select[] = 'um."ID" as id_user';
        }
        if ($needOrderByUsername) {
            $select[] = 'um."Username" as username';
        }
        $selectClause  = "select "
            . implode(', ', $select)
            . ' from relay.users_main um '
            . implode(' ', $this->join)
            . " $where $orderByClause $limitClause";
        if (!$needTrackerip) {
            // easy
            $this->pageSql = $this->cteSql() . $selectClause;
        } else {
            $cte = "result as ($selectClause)";
            if (!isset($this->cte['trackerip'])) {
                // if we are already filtering on tracker ip then the trackerip CTE
                // is present. If we only want to view tracker ips as a result column,
                // then the CTE needs to be added, now that the user ids are known.
                $cte .= ',ith(id_user, ip) as (
                    select distinct uid,
                        first_value(ip) over (partition by uid order by mtime desc)
                    from relay.xbt_files_users
                    where uid in (select id_user from result)
                )';
            }
            $this->cte['result'] = $cte;
            // drop the trailing id_user
            array_pop($select);
            $resultSelect = [];
            foreach ($select as $column) {
                if (str_contains($column, 'null/*resolve*/')) {
                    $resultSelect[] = str_replace('null/*resolve*/', 'ith.ip', $column);
                } else {
                    // e.g. from um."Email" as c0, all we need is c0
                    $resultSelect[] = array_last(explode(' ', $column));
                }
            }
            $this->pageSql = $this->cteSql()
                . ' select '
                . implode(', ', $resultSelect)
                . " from result left join ith using (id_user) $orderByClause";
        }
        return $this->pageSql;
    }

    public function page(int $limit, int $offset): array {
        $result = $this->pg()->allex(
            $this->pageSql(),
            ...[...$this->args, $limit, $offset],
        );
        if ($result !== [] && array_key_exists('username', array_first($result))) {
            // get rid of surrogate column used for ordering
            foreach ($result as &$row) {
                unset($row['username']);
            }
            unset($row);
        }
        return $result;
    }

    public function heading(): SortableTableHeader {
        if (isset($this->heading)) {
            return $this->heading;
        }
        $list = [];
        foreach ($this->column as $name) {
            $list[$name] = [
                'dbColumn'    => $this->columnMap($name),
                'defaultSort' => $this->naturalOrder($name),
                'text'        => $this->headingMap($name),
                'alias'       => 'c' . count($list),
            ];
        }
        $this->heading = new SortableTableHeader($this->column[0], $list, $this->input);
        return $this->heading;
    }
}
