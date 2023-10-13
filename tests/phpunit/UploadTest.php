<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class UploadTest extends TestCase {
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('upload.' . randomString(6), 'upload');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testUpload(): void {
        $upload = new \Gazelle\Upload($this->user);

        $this->assertInstanceOf(\Gazelle\Upload::class, $upload->setCategoryId(CATEGORY_MUSIC), 'upload-music');
        $this->assertStringContainsString($this->user->auth(), $upload->head(), 'upload-head');
        $this->assertStringContainsString(
            '<input id="post" type="submit" value="Upload torrent" />',
            $upload->foot(true),
            'upload-foot'
        );

        $this->assertEquals("TextareaPreview.factory([[0, 'album_desc'],[1, 'release_desc']]);", $upload->albumReleaseJS(), 'upload-album-js');
        $this->assertEquals("TextareaPreview.factory([[0, 'desc']]);", $upload->descriptionJS(), 'upload-description-js');

        $form = $upload->music_form(['acoustic', 'baroque.era', 'chillout'], new \Gazelle\Manager\TGroup);
        $this->assertStringStartsWith('        <div id="musicbrainz_popup"', $form, 'upload-music-popup');
        $this->assertStringContainsString('        <table id="form-music-upload"', $form, 'upload-music-begin');
        $this->assertStringContainsString('chillout', $form, 'upload-music-form');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-music-end');

        $form = $upload->audiobook_form();
        $this->assertStringStartsWith('        <table id="form-audiobook"', $form, 'upload-audiobook-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-audiobook-end');

        $form = $upload->simple_form();
        $this->assertStringStartsWith('        <table id="form-simple-upload"', $form, 'upload-simple-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-simple-end');
    }
}
