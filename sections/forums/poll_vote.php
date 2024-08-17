<?php
/** @phpstan-var \Gazelle\User $Viewer */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

$poll = (new Gazelle\Manager\ForumPoll())->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    error(404, true);
}
if ($poll->isClosed()) {
    error(403, true);
}

$vote = $poll->vote();
if (!isset($_POST['vote']) || !is_number($_POST['vote'])) {
?>
<span class="error">Please select an option.</span><br />
<form class="vote_form" name="poll" id="poll" action="">
    <input type="hidden" name="action" value="poll" />
    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
    <input type="hidden" name="threadid" value="<?= $poll->id() ?>" />
<?php foreach ($vote as $i => $choice) { ?>
    <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
    <label for="answer_<?=$i?>"><?=display_str($choice['answer'])?></label><br />
<?php } ?>
    <br /><input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank&#8202;&mdash;&#8202;Show the results!</label><br /><br />
    <input type="button" onclick="ajax.post('index.php', 'poll', function(response) { $('#poll_container').raw().innerHTML = response });" value="Vote" />
</form>
<?php
} else {
    authorize();
    $response = (int)$_POST['vote'];
    if (!$poll->addVote($Viewer, $response)) {
        error(0, true);
    }
    $vote = $poll->vote(); // need to refresh the results to take the vote into account

    if ($response !== 0) {
        $vote[$response]['answer'] = '&raquo; ' . $vote[$response]['answer'];
    }
?>
        <ul class="poll nobullet">
<?php
        if ($poll->hasRevealVotes()) {
            $staffVote = $poll->staffVote(new Gazelle\Manager\User());
            foreach ($staffVote as $response => $info) {
                if ($response !== 'missing') {
?>
                <li><a href="forums.php?action=change_vote&amp;threadid=<?= $poll->id() ?>&amp;auth=<?= $Viewer->auth() ?>&amp;vote=<?= $response ?>"><?=empty($info['answer']) ? 'Abstain' : display_str($info['answer'])?></a> <?=
                    count ($info['who']) ? (" \xE2\x80\x93 " . implode(', ', array_map(fn($u) => $u->link(), $info['who']))) : "<i>none</i>"
                ?></li>
<?php
                }
            }
            if (count($staffVote['missing']['who'])) {
?>
                <li>Missing: <?= implode(', ', array_map(fn($u) => $u->link(), $staffVote['missing']['who'])) ?></li>
<?php
            }
        } else {
            foreach ($vote as $choice) {
?>
                    <li><?=display_str($choice['answer'])?> (<?=number_format($choice['percent'], 2)?>%)</li>
                    <li class="graph">
                        <span class="left_poll"></span>
                        <span class="center_poll" style="width: <?=number_format($choice['ratio'], 2)?>%;"></span>
                        <span class="right_poll"></span>
                    </li>
<?php
            }
        }
?>
        </ul>
        <br /><strong>Votes:</strong> <?= number_format($poll->total()) ?>
<?php
}
