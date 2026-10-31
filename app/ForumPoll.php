<?php

declare(strict_types=1);

namespace Gazelle;

class ForumPoll extends BasePgObject {
    final public const tableName    = 'forum_poll';
    final public const pkName       = 'id_forum_thread';
    final protected const CACHE_KEY = 'fpoll_%d';

    public function __construct(ForumThread $thread) {
        parent::__construct($thread->id);
    }

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        new Manager\ForumPoll()->flush();
        return $this;
    }

    public function link(): string {
        return $this->thread()->link();
    }

    public function location(): string {
        return "forums.php?action=viewthread&threadid={$this->id}";
    }

    public function thread(): ForumThread {
        return new ForumThread($this->id);
    }

    public function info(): array {
        if (!isset($this->info)) {
            $key = sprintf(self::CACHE_KEY, $this->id);
            $info = self::$cache->get_value($key);
            if ($info === false) {
                $info = $this->pg()->rowAssocex("
                    with tally(vote, total) as (
                        -- true votes
                        select vote,
                            count(*)
                        from forum_poll_vote
                        where id_forum_thread = $1
                            and vote != 0
                        group by vote
                    ),
                    grand(total, highest) as (
                        -- total votes (to calculate percentages)
                        select
                            coalesce(sum(total), 0)::integer,
                            coalesce(max(total), 0)::integer
                        from tally
                    ),
                    abstention(total) as (
                        select count(*)
                        from forum_poll_vote
                        where id_forum_thread = $1
                            and vote = 0
                    ),
                    results as (
                        -- gather up the vote tallies
                        select
                            ans.vote,
                            jsonb_build_object(
                                'answer', ans.label,
                                'score', coalesce(t.total, 0),
                                'ratio', coalesce(
                                    t.total::double precision / nullif(grand.total, 0),
                                    0.0::double precision
                                )
                            ) as result
                        from forum_poll as fp
                        cross join lateral jsonb_each(fp.answer) as ans(vote, label)
                        cross join grand
                        left join tally as t on (t.vote::text = ans.vote)
                        where fp.id_forum_thread = $1
                        union all
                        -- and add the abstentions
                        select
                            '0',
                            jsonb_build_object(
                                'answer', '(Abstain)',
                                'score', a.total,
                                'ratio', 0.0::double precision
                            )
                        from abstention as a
                    ),
                    poll as (
                        -- the details of the poll itself
                        select
                            question,
                            answer,
                            created,
                            is_closed,
                            is_featured
                        from forum_poll
                        where id_forum_thread = $1
                    )
                    select
                        p.question,
                        p.created,
                        p.is_closed,
                        p.is_featured,
                        p.answer      as answer_list,
                        grand.total   as total,
                        grand.highest as highest,
                        jsonb_object_agg(vote, result) as tally
                    from results
                    cross join grand
                    cross join poll p
                    group by
                        p.question,
                        p.answer,
                        p.created,
                        p.is_closed,
                        p.is_featured,
                        grand.total,
                        grand.highest
                    ", $this->id
                );
                self::$cache->cache_value($key, $info, 86400);
            }
            $this->info = $info;
        }
        return $this->info;
    }

    public function isClosed(): bool {
        return $this->info()['is_closed'];
    }

    public function isFeatured(): bool {
        return $this->info()['is_featured'];
    }

    /**
     * @return array<string>
     */
    public function answerList(): array {
        return $this->info()['answer_list'];
    }

    public function max(): int {
        return $this->info()['highest'];
    }

    public function question(): string {
        return $this->info()['question'];
    }

    public function total(): int {
        return $this->info()['total'];
    }

    public function close(): int {
        $affected = $this->pg()->query('
            update forum_poll set
                is_closed = true
            where id_forum_thread = $1
            ', $this->id
        );
        $this->flush();
        return $affected;
    }

    /**
     * The current tally of votes
     * @return array<int, array{answer: string, score: int, ratio: float}>
     */
    public function vote(): array {
        return $this->info()['tally'];
    }

    public function hasRevealVotes(): bool {
        return $this->thread()->forum()->hasRevealVotes();
    }

    protected function saveAnswerList(array $answerList): int {
        $affected = $this->pg()->query('
            update forum_poll set
                answer = $1
            where id_forum_thread = $2
            ', json_encode($answerList), $this->id
        );
        $this->flush();
        return $affected;
    }

    /**
     * Add a new answer to an existing poll. Find the highest existing answer value
     * and save the new answer as highest + 1
     *
     * @return int 1 if the answer was added, otherwise 0
     */
    public function addAnswer(string $answer): int {
        $answerList = $this->answerList();
        $idx = array_keys($answerList);
        $answerList[(int)array_last($idx) + 1] = $answer;
        return $this->saveAnswerList($answerList);
    }

    public function removeAnswer(int $item): int {
        $answerList = $this->answerList();
        if (!$answerList) {
            return 0;
        }
        unset($answerList[$item]);
        $affected = $this->pg()->query('
            delete from forum_poll_vote
            where id_forum_thread = $1
                and vote = $2
            ', $this->id, $item
        );
        $this->flush();
        return $affected;
    }

    public function addVote(User $user, int $vote): int {
        $answerList = $this->answerList();
        if (!isset($answerList[$vote]) && $vote !== 0) {
            return 0;
        }
        $affected = $this->pg()->query('
            insert into forum_poll_vote
                   (id_forum_thread, id_user, vote)
            values ($1,              $2,      $3)
            on conflict (id_forum_thread, id_user)
                do nothing
            ', $this->id, $user->id, $vote
        );
        $this->flush();
        return $affected;
    }

    public function modifyVote(User $user, int $vote): int {
        $affected = $this->pg()->query('
            update forum_poll_vote set
                vote = $3
            where id_forum_thread = $1
                and id_user       = $2
            ', $this->id, $user->id, $vote
        );
        $this->flush();
        return $affected;
    }

    public function response(User $user): ?int {
        return $this->pg()->scalarex('
            select vote
            from forum_poll_vote
            where id_user = $1
                and id_forum_thread = $2
            ', $user->id, $this->id
        );
    }

    /**
     * Return the nominative results. Each vote response contains
     * an array 'who' of User objects who have voted for that response.
     * There is also a 'missing' key that lists all staff members who
     * have not yet voted on the poll.
     *
     * @param Manager\User $userMan a user manager object to hydrate user ids.
     * @return array of votes
     */
    public function staffVote(
        Manager\User $userMan = new Manager\User(),
    ): array {
        $vote = $this->vote();
        $vote['missing'] = ['who' => []];
        foreach ($vote as &$v) {
            $v['who'] = [];
        }
        unset($v);
        $result = $this->pg()->pair(
            'id_user',
            'response',
            <<<END_SQL
            select um."ID" as id_user,
                fpv.vote   as response
            from relay.users_main        um
            inner join relay.permissions p   on (p."ID" = um."PermissionID")
            left join forum_poll_vote    fpv on (fpv.id_user = um."ID" and fpv.id_forum_thread = $1)
            where p."Level" >= (
                select "Level" from relay.permissions where "ID" = $2
            )
            END_SQL, $this->id, FORUM_MOD
        );
        foreach ($result as $userId => $response) {
            $vote[$response ?? 'missing']['who'][] = $userMan->findById((int)$userId);
        }
        return $vote;
    }

    public function modify(): bool {
        $affected =  parent::modify();
        if ($affected) {
            new Manager\ForumPoll()->flush();
        }
        return $affected;
    }

    public function remove(): int {
        $affected = $this->pg()->query('
            delete from forum_poll where id_forum_thread = $1
            ', $this->id
        );
        $this->flush();
        if ($affected) {
            new Manager\ForumPoll()->flush();
        }
        return $affected;
    }
}
