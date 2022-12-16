<?php

namespace Gazelle\API;

class Request extends AbstractAPI {
    public function run() {
        $request = (new \Gazelle\Manager\Request)->findById((int)($_GET['request_id'] ?? 0));
        if (is_null($request)) {
            json_error('Missing request id');
        }

        return [
            'ID'              => $request->id(),
            'UserID'          => $request->userId(),
            'TimeAdded'       => $request->created(),
            'LastVote'        => $request->lastVoteDate(),
            'CategoryID'      => $request->categoryId(),
            'Title'           => $request->title(),
            'Year'            => $request->year(),
            'Image'           => (string)$request->image(),
            'Description'     => $request->description(),
            'CatalogueNumber' => $request->catalogueNumber(),
            'RecordLabel'     => $request->recordLabel(),
            'ReleaseType'     => $request->releaseType(),
            'BitrateList'     => $request->legacyEncodingList(),
            'FormatList'      => $request->legacyFormatList(),
            'MediaList'       => $request->legacyMediaList(),
            'LogCue'          => $request->legacyLogCue(),
            'Checksum'        => $request->legacyLogChecksum(),
            'FillerID'        => $request->fillerId(),
            'TorrentID'       => $request->torrentId(),
            'TimeFilled'      => (string)$request->fillDate(),
            'GroupID'         => (string)$request->tgroupId(),
            'OCLC'            => $request->legacyOclc(),
            'Tags'            => $request->tagNameList(),
            'Artists'         => $request->artistRole()->idList(),
            'DisplayArtists'  => $request->artistRole()->text(),
            'Category'        => $request->categoryName(),
        ];
    }
}
