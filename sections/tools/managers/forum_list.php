<?php
function class_list($Selected = 0) {
    $Classes = (new Gazelle\Manager\User)->classList();
    $Return = '';
    foreach ($Classes as $ID => $Class) {
        if ($Class['Secondary']) {
            continue;
        }

        $Name = $Class['Name'];
        $Level = $Class['Level'];
        $Return .= "<option value=\"$Level\"";
        if ($Selected == $Level) {
            $Return .= ' selected="selected"';
        }
        $Return .= '>'.shortenString($Name, 20, true)."</option>\n";
    }
    return $Return;
}

if (!check_perms('admin_manage_forums')) {
    error(403);
}

$forumMan = new \Gazelle\Manager\Forum;
$ForumArray = $forumMan->nameList();
$ForumCats = $forumMan->categoryList();
$toc = $forumMan->tableOfContentsMain();

View::show_header('Forum Management');
?>
<div class="header">
    <script type="text/javacript">document.getElementByID('content').style.overflow = 'visible';</script>
    <h2>Forum control panel</h2>
</div>
<div class="linkbox">
    <a class="brackets" href="tools.php?action=categories">Forum Categories</a>
    <a class="brackets" href="tools.php?action=forum_transitions">Forum Transitions</a>
</div>
<table>
    <tr class="colhead">
        <td>Category</td>
        <td>Sort</td>
        <td>Name</td>
        <td>Description</td>
        <td colspan="2">Min class<br />Read/Write/Create</td>
        <td>Auto-lock</td>
        <td>Auto-lock<br />weeks</td>
        <td>Submit</td>
    </tr>
<?php
$Row = 'b';
$auth = $Viewer->auth();
foreach ($toc as $category => $forumList) {
    foreach ($forumList as $f) {
        $Row = $Row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$Row?>">
        <form class="manage_form" name="forums" action="" method="post">
            <input type="hidden" name="id" value="<?= $f['ID'] ?>" />
            <input type="hidden" name="action" value="forum_alter" />
            <input type="hidden" name="auth" value="<?= $auth ?>" />
            <td>
                <select name="categoryid">
<?php
        foreach ($ForumCats as $CurCat => $CatName) {
?>
                    <option value="<?= $CurCat ?>"<?= ($CurCat == $f['categoryId']) ? ' selected="selected"' : '' ?>><?= $CatName ?></option>
<?php   } ?>
            </td>
            <td>
                <input type="text" size="3" name="sort" value="<?= $f['Sort'] ?>" />
            </td>
            <td>
                <input type="text" size="10" name="name" value="<?= $f['Name'] ?>" />
            </td>
            <td>
                <input type="text" size="20" name="description" value="<?= $f['Description'] ?>" />
            </td>
            <td>R<br />W<br />C</td>
            <td>
                <select name="minclassread">
                    <?=class_list($f['MinClassRead'])?>
                </select><br />
                <select name="minclasswrite">
                    <?=class_list($f['MinClassWrite'])?>
                </select><br />
                <select name="minclasscreate">
                    <?=class_list($f['MinClassCreate'])?>
                </select>
            </td>
            <td>
                <input type="checkbox" name="autolock"<?= $f['AutoLock'] ? ' checked="checked"' : '' ?> />
            </td>
            <td>
                <input type="text" size="4" name="autolockweeks" value="<?= $f['AutoLockWeeks'] ?>" />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this forum? This is an irreversible action!')"/>
            </td>
        </form>
    </tr>
<?php
    }
}
?>
    <tr class="colhead">
        <td colspan="9">Create forum</td>
    </tr>
    <tr class="rowa">
        <form class="create_form" name="forum" action="" method="post">
            <input type="hidden" name="action" value="forum_alter" />
            <input type="hidden" name="auth" value="<?= $auth ?>" />
            <td>
                <select name="categoryid">
<?php foreach ($ForumCats as $CurCat => $CatName) { ?>
                    <option value="<?= $CurCat ?>"><?=$CatName?></option>
<?php } ?>
                </select>
            </td>
            <td>
                <input type="text" size="3" name="sort" />
            </td>
            <td>
                <input type="text" size="10" name="name" />
            </td>
            <td>
                <input type="text" size="20" name="description" />
            </td>
            <td>R<br />W<br />C</td>
            <td>
                <select name="minclassread">
                    <?=class_list()?>
                </select><br />
                <select name="minclasswrite">
                    <?=class_list()?>
                </select><br />
                <select name="minclasscreate">
                    <?=class_list()?>
                </select>
            </td>
            <td>
                <input type="checkbox" name="autolock" />
            </td>
            <td>
                <input type="text" size="4" name="autolockweeks" value="52" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
</table>
<?php
View::show_footer();
