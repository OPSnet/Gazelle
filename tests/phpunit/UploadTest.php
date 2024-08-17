<?php

use PHPUnit\Framework\TestCase;

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

        $this->assertStringContainsString($this->user->auth(), $upload->head(0), 'upload-head');

        Gazelle\Base::setRequestContext(new Gazelle\BaseRequestContext('/upload.php', '127.0.0.1', ''));
        global $SessionID;
        $SessionID = '';
        global $Viewer;
        $Viewer = $this->user;
        $this->assertStringContainsString(
            '<input id="post" type="submit" value="Upload torrent" />',
            $upload->foot(true),
            'upload-foot'
        );

        $this->assertEquals("TextareaPreview.factory([[0, 'album_desc'],[1, 'release_desc']]);", $upload->albumReleaseJS(), 'upload-album-js');
        $this->assertEquals("TextareaPreview.factory([[0, 'desc']]);", $upload->descriptionJS(), 'upload-description-js');

        $textarea = $upload->textarea('t', 'contents')->emit();
        $this->assertStringContainsString('<textarea name="t" ', $textarea, 'upload-textarea-name');
        $this->assertStringContainsString('>contents<', $textarea, 'upload-textarea-contents');

        $form = $upload->application();
        $this->assertStringStartsWith('<table id="form-application-upload"', $form, 'upload-application-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-application-end');

        $form = $upload->audiobook();
        $this->assertStringStartsWith('<table id="form-audiobook-upload"', $form, 'upload-audiobook-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-audiobook-end');

        $form = $upload->comedy();
        $this->assertStringStartsWith('<table id="form-comedy-upload"', $form, 'upload-comedy-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-comedy-end');

        $form = $upload->comic();
        $this->assertStringStartsWith('<table id="form-comic-upload"', $form, 'upload-comic-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-comic-end');

        $form = $upload->ebook();
        $this->assertStringStartsWith('<table id="form-ebook-upload"', $form, 'upload-ebook-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-ebook-end');

        $form = $upload->elearning();
        $this->assertStringStartsWith('<table id="form-elearning-upload"', $form, 'upload-elearning-begin');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-elearning-end');

        $form = $upload->music(['acoustic', 'baroque.era', 'chillout'], new \Gazelle\Manager\TGroup());
        $this->assertStringStartsWith('<div id="musicbrainz_popup"', $form, 'upload-music-popup');
        $this->assertStringContainsString('table id="form-music-upload"', $form, 'upload-music-begin');
        $this->assertStringContainsString('chillout', $form, 'upload-music-form');
        $this->assertStringEndsWith("</table>\n", $form, 'upload-music-end');
    }
}
