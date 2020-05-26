<?php
View::show_header('Top 10 Donors');

?>
<div class="thin">
    <div class="header">
        <h2>Top Donors</h2>
        <?php \Gazelle\Top10::renderLinkbox("donors"); ?>
    </div>
<?php

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

$isMod = check_perms("users_mod");

$donor = new \Gazelle\Top10\Donor;
$results = $donor->getTopDonors($limit);
?>
<h3>Top <?="$limit";?> Donors
  <small class="top10_quantity_links">
  <?php
  switch ($limit) {
    case 100: ?>
      - <a href="top10.php?type=donors" class="brackets">Top 10</a>
      - <span class="brackets">Top 100</span>
      - <a href="top10.php?type=donors&amp;limit=250" class="brackets">Top 250</a>
    <?php    break;
    case 250: ?>
      - <a href="top10.php?type=donors" class="brackets">Top 10</a>
      - <a href="top10.php?type=donors&amp;limit=100" class="brackets">Top 100</a>
      - <span class="brackets">Top 250</span>
    <?php    break;
    default: ?>
      - <span class="brackets">Top 10</span>
      - <a href="top10.php?type=donors&amp;limit=100" class="brackets">Top 100</a>
      - <a href="top10.php?type=donors&amp;limit=250" class="brackets">Top 250</a>
  <?php } ?>
  </small></h3>

  <table class="border">
    <tr class="colhead">
      <td class="center">Position</td>
      <td>User</td>
      <td style="text-align: left;">Total Donor Points</td>
      <td style="text-align: left;">Current Donor Rank</td>
      <td style="text-align: left;">Last Donated</td>
    </tr>


<?php if (empty($results)) { ?>
  <tr class="rowb">
      <td colspan="9" class="center">
          Found no users matching the criteria
      </td>
  </tr>
  </table><br>
<?php } ?>

<?php
  foreach($results as $index=>$donor) {
    $highlight = ($index % 2 ? 'a' : 'b');
?>
    <tr class="row<?=$highlight?>">
        <td class="center"><?=$index + 1?></td>
        <td><?=$donor['Hidden'] && !$isMod ? 'Hidden' : Users::format_username($donor['UserID'], false, false, false)?></td>
        <td style="text-align: left;"><?=check_perms('users_mod') || $index < 51 ? $donor['TotalRank'] : 'Hidden';?></td>
        <td style="text-align: left;"><?=$donor['Hidden'] && !$isMod ? 'Hidden' : DonationsView::render_rank($donor['Rank'], $donor['SpecialRank'])?></td>
        <td style="text-align: left;"><?=$donor['Hidden'] && !$isMod ? 'Hidden' : time_diff($donor['DonationTime'])?></td>
    </tr>
  <?php } ?>
  </table>
  <br>
</div>
<?php View::show_footer();
