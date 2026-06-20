<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MergeTimerange extends AbstractMigration {
    public function up(): void {
        $this->query("
            CREATE OR REPLACE FUNCTION merge_ranges_by_gap(
                range tstzmultirange,
                gap interval
            ) RETURNS tstzmultirange
            LANGUAGE plpgsql IMMUTABLE STRICT
            AS $$
            DECLARE
                r tstzrange;
                cur tstzmultirange;
                result tstzmultirange := tstzmultirange();
            BEGIN
                FOR r IN
                    SELECT unnest(range)
                LOOP
                    IF cur IS NULL THEN
                        cur := tstzmultirange(r);
                    ELSIF lower(r) <= upper(cur) + gap THEN
                        cur := tstzmultirange(tstzrange(
                            lower(cur), upper(r), '[]'
                        ));
                    ELSE
                        result := result + cur;
                        cur := tstzmultirange(r);
                    END IF;
                END LOOP;
            
                IF cur IS NOT NULL THEN
                    result := result + cur;
                END IF;
            
                RETURN result;
            END;
            $$
        ");
    }

    public function down(): void {
        $this->query("
            drop function merge_ranges_by_gap
        ");
    }
}
