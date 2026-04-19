<?php
/** @phpstan-var \Gazelle\User $Viewer */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

declare(strict_types=1);

namespace Gazelle;

$threadId = (int)($_POST['threadid'] ?? 0);
$poll = new Manager\ForumPoll()->findById($threadId);
if (is_null($poll)) {
    Error404::error("No reference to that poll found");
}
if ($poll->isClosed()) {
    Error403::error("Sorry, the poll is closed");
}

$answerList = $poll->answerList();
$vote = $poll->vote();
if (!isset($_POST['vote']) || !is_number($_POST['vote'])) {
?>
<span class="error">Please select an option.</span><br>
<form class="vote_form" name="poll" id="poll" action="">
    <input type="hidden" name="action" value="poll">
    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>">
    <input type="hidden" name="threadid" value="<?= $poll->id ?>">
<?php foreach ($answerList as $i => $answer) { ?>
    <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>">
    <label for="answer_<?=$i?>"><?=display_str($answer)?></label><br>
<?php } ?>
    <br><input type="radio" name="vote" id="answer_0" value="0"> <label for="answer_0">Blank&nbsp;&mdash;&nbsp;Show the results!</label><br><br>
    <input type="button" onclick="ajax.post('index.php', 'poll', function(response) { $('#poll_container').raw().innerHTML = response });" value="Vote">
</form>
<?php
} else {
    authorize();
    $response = (int)$_POST['vote'];
    if (!$poll->addVote($Viewer, $response)) {
        Error400::error('Cannot add your vote to the poll');
    }
    $vote = $poll->vote(); // need to refresh the results to take the vote into account
?>
        <ul class="poll nobullet" id="poll_options">
<?php
    if ($poll->hasRevealVotes()) {
        $totalStaff = 0;
        $totalVoted = 0;
        $staffVote = $poll->staffVote(new Manager\User());
        $staffVoteReordered = array_slice($staffVote, 1, null, true) + [0 => $staffVote[0]];
        foreach ($staffVoteReordered as $response => $info) {
            if ($response !== 'missing') {
                $totalStaff += count($info['who']);
                $totalVoted += count($info['who']);
?>
            <li><a href="forums.php?action=change_vote&amp;threadid=<?= $poll->id ?>&amp;auth=<?= $Viewer->auth() ?>&amp;vote=<?= $response ?>"><?=empty($info['answer']) ? 'Abstain' : display_str($info['answer'])?></a> <?=
                count ($info['who'])
                    ? (" – " . implode(', ', array_map(fn ($u) => $u->link(), $info['who'])))
                    : "<i>none</i>"
            ?></li>
<?php
            }
        }
        if (count($staffVote['missing']['who'])) {
            $totalStaff += count($staffVote['missing']['who']);
?>
            <li><br>Missing: <?= implode(', ', array_map(fn ($u) => $u->link(), $staffVote['missing']['who'])) ?></li>
<?php } ?>
        </ul>
        <br>
        <strong>Voted:</strong> <?= number_format($totalVoted) ?> of <?= number_format($totalStaff) ?> total. (You may click on a choice to change your vote).
        <br>
        <a href="#" onclick="AddPollOption(<?= $threadId ?>); return false;" class="brackets">+</a>
<?php
    } else {
        foreach ($answerList as $i => $answer) {
            $choice = $vote[$i];
?>
            <li><?=$response === $i ? '➔ ' : ''?><?=display_str($answer)?> (<?=number_format($choice['percent'], 1)?>%)</li>
            <li class="graph">
                <span class="left_poll"></span>
                <span class="center_poll" style="width: <?=number_format($choice['ratio'], 1)?>%;"></span>
                <span class="right_poll"></span>
            </li>
<?php
       }
       if ($response === 0) {
?>
            <li><br>➔ (Abstain)</li>
<?php   } ?>
        </ul>
        <br><strong>Votes:</strong> <?= number_format($poll->total()) ?>
<?php
    }
}
