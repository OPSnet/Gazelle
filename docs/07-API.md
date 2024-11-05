From: itismadness
To: Developers
Date: 2021-08-21
Subject: Orpheus Development Papers #7 - API Documentation
Version: 2 (2024-11-06)

The JSON API provides an easily parseable interface to Gazelle. The API comes standard in public Gazelle and works out
of the box. Below is the list of information available, the arguments that can be passed to it, and the format of the
results.

You must be logged in to use the API, which can be done in two ways:

1. Website Authentication and Cookie
The default method of authentication is by sending a POST request to `/login.php` with your username and password which
will respond with a cookie that is then used for subsequent requests to the API.

2. API Token
After generating a token on your user profile, you can use it by sending a request with the header
`Authorization: token ${api_token}` or `Authorization: ${api_token}` (deprecated).

NOTE: For the API token, please be aware we heavily discourage people from using the latter form and that it only
exists for the sake of interopability and may go away in the future.

##### Table of Contents

* [Index](#index)
* [User Profile](#user-profile)
* [Messages](#messages)
    * [Inbox](#inbox)
    * [Conversation](#conversation)
* [Top 10](#top-10)
* [User Search](#user-search)
* [Requests Search](#requests-search)
* [Bookmarks](#bookmarks)
* [Subscriptions](#subscriptions)
* [Forums](#forums)
    * [Category View](#category-view)
    * [Forum View](#forum-view)
    * [Thread View](#thread-view)
* [Artist](#artist)
* [Torrents](#torrents)
    * [Torrent Search](#torrent-search)
    * [Torrent Group](#torrent-group)
    * [Add Tag](#add-tag)
    * [Torrent](#torrent)
    * [Upload](#upload)
    * [Download](#download)
    * [Add Log](#add-log)
* [Logchecker](#logchecker)
* [Requests](#requests)
    * [Request](#request)
    * [Request Fill](#request-fill)
* [Collages](#collages)
* [Notifications](#notifications)
* [Announcements](#announcements)
* [Give FL Tokens](#give-fl-tokens)
* [Unofficial projects that utilize the API](#unofficial-projects-that-utilize-the-api)

Questions about the API can be answered in `#develop`.

**Using the API bestows upon you a certain level of trust and responsibility. Abusing or using this API for malicious
purposes is a bannable offense and will not be taken lightly.

Refrain from making more than five (5) requests every ten (10) seconds.**

### Outline

All request URLs are in the form:
`ajax.php?action=<ACTION>`

All the JSON returned is in the form:

```json
{
    "status" : "success",
    "response" : {
        // Response data.
    },
    "info": {
        "source": "Gazelle Dev",
        "version": 1
    }
}
```

If the request is invalid, or a problem occurs, the `status` will be `failure`. In this case the value of `response` is
`undefined`.

## Index

**URL:**
`ajax.php?action=index`

**Arguments:** None

**Response format:**

```json
{
    "status": "success",
    "response": {
        "username": "dr4g0n",
        "id": 469,
        "authkey": "redacted",
        "passkey": "redacted",
        "notifications": {
            "messages": 0,
            "notifications": 9000,
            "newAnnouncement": false,
            "newBlog": false
        },
        "userstats": {
            "uploaded": 585564424629,
            "downloaded": 177461229738,
            "ratio": 3.29,
            "requiredratio": 0.6,
            "bonusPoints": 220903,
            "bonusPointsPerHour": 1.28,
            "class": "VIP"
        }
    }
}
```

## User Profile

**URL:**
`ajax.php?action=user`

**Arguments:**

`id` - id of the user to display

**Response format:**

```json
{
    "status": "success",
    "response": {
        "username": "dr4g0n",
        "avatar": "http://v0lu.me/rubadubdub.png",
        "isFriend": false,
        "profileText": "",
        "stats": {
            "joinedDate": "2007-10-28 14:26:12",
            "lastAccess": "2012-08-09 00:17:52",
            "uploaded": 585564424629,
            "downloaded": 177461229738,
            "ratio": 3.3,
            "requiredRatio": 0.6
        },
        "ranks": {
            "uploaded": 98,
            "downloaded": 95,
            "uploads": 85,
            "requests": 0,
            "bounty": 79,
            "posts": 98,
            "artists": 0,
            "overall": 85
        },
        "personal": {
            "class": "VIP",
            "paranoia": 0,
            "paranoiaText": "Off",
            "donor": true,
            "warned": false,
            "enabled": true,
            "passkey": "redacted"
        },
        "community": {
            "posts": 863,
            "torrentComments": 13,
            "collagesStarted": 0,
            "collagesContrib": 0,
            "requestsFilled": 0,
            "requestsVoted": 13,
            "perfectFlacs": 2,
            "uploaded": 29,
            "groups": 14,
            "seeding": 309,
            "leeching": 0,
            "snatched": 678,
            "invited": 7
        }
    }
}
```

## Messages

### Inbox

**URL:**
`ajax.php?action=inbox`

**Arguments:**

`page` - page number to display (default: 1)

`type` - one of: inbox or sentbox (default: inbox)

`sort` - if set to [i]unread[/i] then unread messages come first

`search` - filter messages by search string

`searchtype` - one of: subject, message, user

**Response format:**

```json
{
    "status": "success",
    "response": {
        "currentPage": 1,
        "pages": 3,
        "messages": [
            {
                "convId": 3421929,
                "subject": "1 of your torrents has been deleted for inactivity",
                "unread": false,
                "sticky": false,
                "forwardedId": 0,
                "forwardedName": "",
                "senderId": 0,
                "username": "",
                "donor": false,
                "warned": false,
                "enabled": true,
                "date": "2012-06-12 00:54:01"
            },
            // ...
        ]
    }
}
```

### Conversation

**URL:**
`ajax.php?action=inbox&type=viewconv`

**Arguments:**

`id` - id of the message to display

**Response format:**

```json
{
    "status": "success",
    "response": {
        "convId": 3421929,
        "subject": "1 of your torrents has been deleted for inactivity",
        "sticky": false,
        "messages": [
            {
                "messageId": 4507261,
                "senderId": 0,
                "senderName": "System",
                "sentDate": "2012-06-12 00:54:01",
                "bbBody": "One of your uploads has been deleted for being unseeded.  Since it didn't break any rules (we hope), please feel free to re-upload it.\n\nThe following torrent was deleted:\nRa - To Sirius [MP3 / 320]",
                "body": "One of your uploads has been deleted for being unseeded.  Since it didn't break any rules (we hope), please feel free to re-upload it.<br />\n<br />\nThe following torrent was deleted:<br />\nRa - To Sirius [MP3 / 320]"
            }
        ]
    }
}
```


## Top 10

**URL:** `ajax.php?action=top10`

### Arguments

#### `type` - Specifies the type of top 10 list to retrieve

- `torrents` (Default)
- `users`
- `tags`

#### `details` - Category for the selected `type`. The available options vary depending on the value of `type`

- `all`: Lists all categories for the selected type (Default)

##### When `type` = "torrents":

- `day`: Most Active Torrents Uploaded in the Past Day
- `week`: Most Active Torrents Uploaded in the Past Week
- `month`: Most Active Torrents Uploaded in the Past Month
- `year`: Most Active Torrents Uploaded in the Past Year
- `overall`: Most Active Torrents Uploaded of All Time
- `snatched`: Most Snatched Torrents
- `data`: Most Data Transferred Torrents
- `seeded`: Best Seeded Torrents

##### When `type` = "users":

- `ul`: Uploaders
- `dl`: Downloaders
- `numul`: Torrents Uploaded
- `uls`: Fastest Uploaders
- `dls`: Fastest Downloaders

##### When `type` = "tags":

- `ut`: Most Used Torrent Tags
- `ur`: Most Used Request Tags
- `v`: Most Highly Voted Tags

#### `limit` - The maximum number of results to return per category. 

Must be one of 10 (default), 100 or 250. When `type`="torrents" and `details`="all", only `limit`="10" is permitted.

## Example Requests

### Example 1: Top 10 Torrents of the Week


**Response format:**

```json
{
    "status": "success",
    "response": [
        {
            "caption": "Most Active Torrents Uploaded in the Past Day",
            "tag": "day",
            "limit": 10,
            "results": [
                {
                    "torrentId": 30194226,
                    "groupId": 72268716,
                    "artist": "2 Chainz",
                    "groupName": "Based on a T.R.U. Story",
                    "groupCategory": 0,
                    "groupYear": 2012,
                    "remasterTitle": "Deluxe Edition",
                    "format": "MP3",
                    "encoding": "V0 (VBR)",
                    "hasLog": false,
                    "hasCue": false,
                    "media": "CD",
                    "scene": true,
                    "year": 2012,
                    "tags": [
                        "hip.hop"
                    ],
                    "snatched": 135,
                    "seeders": 127,
                    "leechers": 5,
                    "data": 17242225550
                },
                // ...
            ]
        },
        {
            "caption": "Most Active Torrents Uploaded in the Past Week",
            "tag": "week",
            "limit": 10,
            "results": [
                {
                    "torrentId": 30186127,
                    "groupId": 72265574,
                    "artist": "Yeasayer",
                    "groupName": "Fragrant World",
                    "groupCategory": 0,
                    "groupYear": 2012,
                    "remasterTitle": "",
                    "format": "MP3",
                    "encoding": "320",
                    "hasLog": false,
                    "hasCue": false,
                    "media": "CD",
                    "scene": false,
                    "year": 0,
                    "tags": [
                        "electronic",
                        "indie",
                        "pop",
                        "psychedelic",
                        "indie.pop"
                    ],
                    "snatched": 2733,
                    "seeders": 1480,
                    "leechers": 7,
                    "data": 323247814656
                },
                // ...
            ]
        },
        {
            "caption": "Most Active Torrents of All Time",
            "tag": "overall",
            "limit": 10,
            "results": [
                {
                    "torrentId": 29729713,
                    "groupId": 72094817,
                    "artist": "The Black Keys",
                    "groupName": "El Camino",
                    "groupCategory": 0,
                    "groupYear": 2011,
                    "remasterTitle": "",
                    "format": "MP3",
                    "encoding": "V0 (VBR)",
                    "hasLog": false,
                    "hasCue": false,
                    "media": "CD",
                    "scene": true,
                    "year": 0,
                    "tags": [
                        "alternative",
                        "indie",
                        "pop",
                        "rock"
                    ],
                    "snatched": 20062,
                    "seeders": 3557,
                    "leechers": 32,
                    "data": 1589584937596
                }
                // ...
            ]
        },
        {
            "caption": "Most Snatched Torrents",
            "tag": "snatched",
            "limit": 10,
            "results": [
                {
                    "torrentId": 374590,
                    "groupId": 206657,
                    "artist": "Various Artists",
                    "groupName": "The What CD",
                    "groupCategory": 0,
                    "groupYear": 2008,
                    "remasterTitle": "",
                    "format": "MP3",
                    "encoding": "V0 (VBR)",
                    "hasLog": false,
                    "hasCue": false,
                    "media": "WEB",
                    "scene": false,
                    "year": 0,
                    "tags": [
                        "alternative",
                        "vanity.house",
                        "dubstep",
                        "hip.hop",
                        "rock",
                        "industrial",
                        "indie",
                        "idm",
                        "experimental",
                        "emo",
                        "electronic",
                        "drum.and.bass",
                        "ambient",
                        "what.cd"
                    ],
                    "snatched": 32006,
                    "seeders": 1318,
                    "leechers": 5,
                    "data": 4411956638772
                },
                // ...
            ]
        },
        {
            "caption": "Most Data Transferred Torrents",
            "tag": "data",
            "limit": 10,
            "results": [
                {
                    "torrentId": 1101103,
                    "groupId": 573597,
                    "artist": "The Beatles",
                    "groupName": "The Beatles Stereo Box Set",
                    "groupCategory": 0,
                    "groupYear": 2009,
                    "remasterTitle": "",
                    "format": "FLAC",
                    "encoding": "Lossless",
                    "hasLog": true,
                    "hasCue": true,
                    "media": "CD",
                    "scene": false,
                    "year": 0,
                    "tags": [
                        "pop",
                        "rock",
                        "classic.rock",
                        "pop.rock"
                    ],
                    "snatched": 23058,
                    "seeders": 1058,
                    "leechers": 12,
                    "data": 91963298927520
                },
                // ...
            ]
        },
        {
            "caption": "Best Seeded Torrents",
            "tag": "seeded",
            "limit": 10,
            "results": [
                {
                    "torrentId": 29729713,
                    "groupId": 72094817,
                    "artist": "The Black Keys",
                    "groupName": "El Camino",
                    "groupCategory": 0,
                    "groupYear": 2011,
                    "remasterTitle": "",
                    "format": "MP3",
                    "encoding": "V0 (VBR)",
                    "hasLog": false,
                    "hasCue": false,
                    "media": "CD",
                    "scene": true,
                    "year": 0,
                    "tags": [
                        "alternative",
                        "indie",
                        "pop",
                        "rock"
                    ],
                    "snatched": 20062,
                    "seeders": 3557,
                    "leechers": 32,
                    "data": 1589584937596
                },
                // ...
            ]
        }
    ]
}
```

## User Search

**URL:**
`ajax.php?action=usersearch`

**Arguments:**

`search` - The search term.

`page` - page to display (default: 1)

**Response format:**

```json
{
    "status": "success",
    "response": {
        "currentPage": 1,
        "pages": 1,
        "results": [
            {
                "userId": 469,
                "username": "dr4g0n",
                "donor": true,
                "warned": false,
                "enabled": true,
                "class": "VIP"
            },
            // ...
        ]
    }
}
```

## Requests Search

**URL:**
`ajax.php?action=requests&search=<term>&page=<page>&tags=<tags>`

**Arguments:**

`search` - search term

`page` - page to display (default: 1)

`tags` - tags to search by (comma separated)

`tags_type` - `0` for any, `1` for match all

`show_filled` - Include filled requests in results - `true` or `false` (default: false).

`filter_cat[]`, `releases[]`, `bitrates[]`, `formats[]`, `media[]` - as used on requests.php

If no arguments are specified then the most recent requests are shown.

**Response format:**

```json
{
    "status": "success",
    "response": {
        "currentPage": 1,
        "pages": 1,
        "results": [
            {
                "requestId": 185971,
                "requestorId": 498,
                "requestorName": "Satan",
                "timeAdded": "2012-05-06 15:43:17",
                "lastVote": "2012-06-10 20:36:46",
                "voteCount": 3,
                "bounty": 245366784,
                "categoryId": 1,
                "categoryName": "Music",
                "artists": [
                    [
                        {
                            "id": "1460",
                            "name": "Logistics"
                        }
                    ],
                    [
                        {
                            "id": "25351",
                            "name": "Alice Smith"
                        },
                        {
                            "id": "44545",
                            "name": "Nightshade"
                        },
                        {
                            "id": "249446",
                            "name": "Sarah Callander"
                        }
                    ]
                ],
                "title": "Fear Not",
                "year": 2012,
                "image": "http://whatimg.com/i/ralpc.jpg",
                "description": "Thank you kindly.",
                "catalogueNumber": "",
                "releaseType": "",
                "bitrateList": "1",
                "formatList": "Lossless",
                "mediaList": "FLAC",
                "logCue": "CD",
                "isFilled": false,
                "fillerId": 0,
                "fillerName": "",
                "torrentId": 0,
                "timeFilled": ""
            },
            // ...
        ]
    }
}
```

## Bookmarks

**URL:**
`ajax.php?action=bookmarks&type=<Type>`

**Arguments:**

`type` - one of torrents, artists (default: torrents)

**Response format:**

Torrents:
```json
{
    "status": "success",
    "response": {
        "bookmarks": [
            {
                "id": 71843824,
                "name": "Spacejams",
                "year": 2010,
                "recordLabel": "Hospital Records",
                "catalogueNumber": "NHS178CD",
                "tagList": "drum_and_bass electronic",
                "releaseType": "1",
                "vanityHouse": false,
                "image": "http://whatimg.com/i/09930203236341542660.jpg",
                "torrents": [
                    {
                        "id": 29043412,
                        "groupId": 71843824,
                        "media": "CD",
                        "format": "FLAC",
                        "encoding": "Lossless",
                        "remasterYear": 0,
                        "remastered": false,
                        "remasterTitle": "",
                        "remasterRecordLabel": "",
                        "remasterCatalogueNumber": "",
                        "scene": false,
                        "hasLog": true,
                        "hasCue": true,
                        "logScore": 100,
                        "fileCount": 15,
                        "freeTorrent": false,
                        "size": 563078107,
                        "leechers": 0,
                        "seeders": 26,
                        "snatched": 142,
                        "time": "2010-11-13 21:25:10",
                        "hasFile": 29043412
                    },
                    // ...
                ]
            }
        ]
    }
}
```

Artists:
```json
{
    "status": "success",
    "response": {
        "artists": [
            {
                "artistId": 1460,
                "artistName": "Logistics"
            }
        ]
    }
}
```

## Subscriptions

**URL:**
`ajax.php?action=subscriptions`

**Arguments:**

`showunread` - 1 to show only unread, 0 for all subscriptions (default: 1)

**Response format:**

```json
{
    "status": "success",
    "response": {
        "threads": [
            {
                "forumId": 20,
                "forumName": "Technology",
                "threadId": 218,
                "threadTitle": "Post Your Desktop",
                "postId": 3844686,
                "lastPostId": 4149355,
                "locked": false,
                "new": true
            },
            // ...
        ]
    }
}
```

## Forums

### Category View

**URL:**
`ajax.php?action=forum&type=main`

**Response format:**

```
{
    "status": "success",
    "response": {
        "categories": [
            {
                "categoryID": 1,
                "categoryName": "Site",
                "forums": [
                    {
                        "forumId": 19,
                        "forumName": "Announcements",
                        "forumDescription": "If you don't like the news, go out and make some of your own.",
                        "numTopics": 338,
                        "numPosts": 84368,
                        "lastPostId": 4148491,
                        "lastAuthorId": 331548,
                        "lastPostAuthorName": "Isocline",
                        "lastTopicId": 150195,
                        "lastTime": "2012-08-08 15:03:18",
                        "specificRules": [],
                        "lastTopic": "Whataroo 2012!",
                        "read": false,
                        "locked": false,
                        "sticky": false
                    },
                    // ...
                ]
            },
            // ...
        ]
    }
}
```

### Forum View

**URL:**
`ajax.php?action=forum&type=viewforum&forumid=<Forum Id>`

**Arguments:**

`forumid` - id of the forum to display

`page` - the page to display (default: 1)

**Response format:**

```
{
    "status": "success",
    "response": {
        "forumName": "Announcements",
        "specificRules": [],
        "currentPage": 1,
        "pages": 7,
        "threads": [
            {
                "topicId": 150195,
                "title": "Whataroo 2012!",
                "authorId": 168713,
                "authorName": "Steve096",
                "locked": false,
                "sticky": false,
                "postCount": 552,
                "lastID": 4148491,
                "lastTime": "2012-08-08 15:03:18",
                "lastAuthorId": 331548,
                "lastAuthorName": "Isocline",
                "lastReadPage": 0,
                "lastReadPostId": 0,
                "read": false
            },
            // ...
        ]
    }
}
```

### Thread View

**URL:**
`ajax.php?action=forum&type=viewthread&threadid=<Thread Id>&postid=<Post Id>`

**Arguments:**

`threadid` - id of the thread to display

`postid` - response will be the page including the post with this id

`page` - page to display (default: 1)

`updatelastread` - set to 1 to not update the last read id (default: 0)

**Response format:**

```json
{
    "status": "success",
    "response": {
        "forumId": 7,
        "forumName": "The Lounge",
        "threadId": 159925,
        "threadTitle": "Women with short hair",
        "subscribed": false,
        "locked": false,
        "sticky": false,
        "currentPage": 1,
        "pages": 1,
        "poll": {
            "closed": false,
            "featured": "0000-00-00 00:00:00",
            "question": "Short or long",
            "maxVotes": 74,
            "totalVotes": 121,
            "voted": false,
            "answers": [
                {
                    "answer": "Short",
                    "ratio": 0.63513513513514,
                    "percent": 0.38842975206612
                },
                {
                    "answer": "Long",
                    "ratio": 1,
                    "percent": 0.61157024793388
                }
            ]
        },
        "posts": [
            {
                "postId": 4146433,
                "addedTime": "2012-08-07 18:38:19",
                "bbBody": "Are so much sexier than when they have long hair. Call me gay or whatever but it's true! There are tons of recognizable examples to choose from so to name a few: Morena Baccarin, Emma Watson, Natalie Portman, Anne Hathaway, etc etc etc.\r\nHere\r\n[img]http://cdn03.cdnwp.celebuzz.com/wp-content/uploads/2010/12/27/emma-watson.jpg[/img][img]http://www.seventeen.com/cm/seventeen/images/sev-emma-watson-short-hair-101810.gif[/img]\r\n\r\n",
                "body": "Are so much sexier than when they have long hair. Call me gay or whatever but it's true! There are tons of recognizable examples to choose from so to name a few: Morena Baccarin, Emma Watson, Natalie Portman, Anne Hathaway, etc etc etc.<br />\r\nHere<br />\r\n<img class=\"scale_image\" onclick=\"lightbox.init(this,500);\" alt=\"http://cdn03.cdnwp.celebuzz.com/wp-content/uploads/2010/12/27/emma-watson.jpg\" src=\"http://cdn03.cdnwp.celebuzz.com/wp-content/uploads/2010/12/27/emma-watson.jpg\" /><img class=\"scale_image\" onclick=\"lightbox.init(this,500);\" alt=\"http://www.seventeen.com/cm/seventeen/images/sev-emma-watson-short-hair-101810.gif\" src=\"http://www.seventeen.com/cm/seventeen/images/sev-emma-watson-short-hair-101810.gif\" /><br />\r\n<br />\r\n",
                "editedUserId": 0,
                "editedTime": "",
                "editedUsername": "",
                "author": {
                    "authorId": 310550,
                    "authorName": "Z0M813",
                    "paranoia": [
                        "collages+",
                        "collagecontribs+"
                    ],
                    "artist": false,
                    "donor": false,
                    "warned": false,
                    "avatar": "http://whatimg.com/i/vmrol8.jpeg",
                    "enabled": true,
                    "userTitle": ""
                }
            },
            // ...
        ]
    }
}
```

## Artist

**URL:**
`ajax.php?action=artist&id=<Artist Id>`

**Arguments:**

`id` - artist's id

`artistname` - Artist's Name

`artistreleases` - if set, only include groups where the artist is the main artist.

**Response format:**

```json
{
    "status": "success",
    "response": {
        "id": 1460,
        "name": "Logistics",
        "notificationsEnabled": false,
        "hasBookmarked": true,
        "image": "http://img120.imageshack.us/img120/3206/logiop1.jpg",
        "body": "",
        "vanityHouse": false,
        "tags": [
            {
                "name": "breaks",
                "count": 3
            },
            // ...
        ],
        "similarArtists": [],
        "statistics": {
            "numGroups": 125,
            "numTorrents": 443,
            "numSeeders": 3047,
            "numLeechers": 95,
            "numSnatches": 28033
        },
        "torrentgroup": [
            {
                "groupId": 72189681,
                "groupName": "Fear Not",
                "groupYear": 2012,
                "groupRecordLabel": "Hospital Records",
                "groupCatalogueNumber": "NHS209CD",
                "tags": [
                    "breaks",
                    "drum.and.bass",
                    "electronic",
                    "dubstep"
                ],
                "releaseType": 1,
                "groupVanityHouse": false,
                "hasBookmarked": false,
                "torrent": [
                    {
                        "id": 29991962,
                        "groupId": 72189681,
                        "media": "CD",
                        "format": "FLAC",
                        "encoding": "Lossless",
                        "remasterYear": 0,
                        "remastered": false,
                        "remasterTitle": "",
                        "remasterRecordLabel": "",
                        "scene": true,
                        "hasLog": false,
                        "hasCue": false,
                        "logScore": 0,
                        "fileCount": 19,
                        "freeTorrent": false,
                        "size": 527749302,
                        "leechers": 0,
                        "seeders": 20,
                        "snatched": 55,
                        "time": "2012-04-14 15:57:00",
                        "hasFile": 29991962
                    },
                    // ...
                ]
            },
            // ...
        ],
        "requests": [
            {
                "requestId": 172667,
                "categoryId": 1,
                "title": "We Are One (Nu:logic Remix)/timelapse",
                "year": 2012,
                "timeAdded": "2012-02-07 03:44:39",
                "votes": 3,
                "bounty": 217055232
            },
            // ...
        ]
    }
}
```

## Torrents

### Torrent Search

**URL:**
`ajax.php?action=browse&searchstr=<Search Term>`

**Arguments:**

`searchstr` - string to search for

`page` - page to display (default: 1)

`taglist`, `tags_type`, `order_by`, `order_way`, `filter_cat`, `freetorrent`, `vanityhouse`, `scene`, `haslog`, `releasetype`, `media`, `format`, `encoding`, `artistname`, `filelist`, `groupname`, `recordlabel`, `cataloguenumber`, `year`, `remastertitle`, `remasteryear`, `remasterrecordlabel`, `remastercataloguenumber` - as in advanced search

**Response format:**

```json
{
    "status": "success",
    "response": {
        "currentPage": 1,
        "pages": 3,
        "results": [
            {
                "groupId": 410618,
                "groupName": "Jungle Music / Toytown",
                "artist": "Logistics",
                "tags": [
                    "drum.and.bass",
                    "electronic"
                ],
                "bookmarked": false,
                "vanityHouse": false,
                "groupYear": 2009,
                "releaseType": "Single",
                "groupTime": 1339117820,
                "maxSize": 237970,
                "totalSnatched": 318,
                "totalSeeders": 14,
                "totalLeechers": 0,
                "torrents": [
                    {
                        "torrentId": 959473,
                        "editionId": 1,
                        "artists": [
                            {
                                "id": 1460,
                                "name": "Logistics",
                                "aliasid": 1460
                            }
                        ],
                        "remastered": false,
                        "remasterYear": 0,
                        "remasterCatalogueNumber": "",
                        "remasterTitle": "",
                        "media": "Vinyl",
                        "encoding": "24bit Lossless",
                        "format": "FLAC",
                        "hasLog": false,
                        "logScore": 79,
                        "hasCue": false,
                        "scene": false,
                        "vanityHouse": false,
                        "fileCount": 3,
                        "time": "2009-06-06 19:04:22",
                        "size": 243680994,
                        "snatches": 10,
                        "seeders": 3,
                        "leechers": 0,
                        "isFreeleech": false,
                        "isNeutralLeech": false,
                        "isPersonalFreeleech": false,
                        "canUseToken": true
                    },
                    // ...
                ]
            },
            // ...
        ]
    }
}
```

### Torrent Group

**URL:**
`ajax.php?action=torrentgroup&id=<Torrent Group Id>`

**Arguments:**

`id` - torrent's group id

`hash` - hash of a torrent in the torrent group (must be uppercase)

**Response format:**

The `proxyImage` field was introduced in version 2 of this endpoint.

Note: the `proxyImage` value has a short lifespan. Depending on (bad) luck,
it may expire (404) one second after the response. It is provided in order
to rehost the image elsewhere when the origin image is no longer available.

```json
{
    "status": "success",
    "response": {
        "group": {
            "wikiBody": "",
            "wikiImage": "http://whatimg.com/i/ralpc.jpg",
            "proxyImage": "http://proxy.com/xyz/abc.jpg",
            "id": 72189681,
            "name": "Fear Not",
            "year": 2012,
            "recordLabel": "Hospital Records",
            "catalogueNumber": "NHS209CD",
            "releaseType": 1,
            "categoryId": 1,
            "categoryName": "Music",
            "time": "2012-05-02 07:39:30",
            "vanityHouse": false,
            "musicInfo": {
                "composers": [],
                "dj": [],
                "artists": [
                    {
                        "id": 1460,
                        "name": "Logistics"
                    }
                ],
                "with": [
                    {
                        "id": 25351,
                        "name": "Alice Smith"
                    },
                    {
                        "id": 44545,
                        "name": "Nightshade"
                    },
                    {
                        "id": 249446,
                        "name": "Sarah Callander"
                    }
                ],
                "conductor": [],
                "remixedBy": [],
                "producer": []
            }
        },
        "torrents": [
            {
                "id": 29991962,
                "media": "CD",
                "format": "FLAC",
                "encoding": "Lossless",
                "remastered": false,
                "remasterYear": 0,
                "remasterTitle": "",
                "remasterRecordLabel": "",
                "remasterCatalogueNumber": "",
                "scene": true,
                "hasLog": false,
                "hasCue": false,
                "logScore": 0,
                "fileCount": 19,
                "size": 527749302,
                "seeders": 20,
                "leechers": 0,
                "snatched": 55,
                "freeTorrent": false,
                "time": "2012-04-14 15:57:00",
                "description": "[URL=http://whatimg.com/viewer.php?file=yvpsp.jpg][IMG]http://whatimg.com/i/yvpsp_thumb.jpg[/IMG][/URL]",
                "fileList": "00-logistics-fear_not-cd-flac-2012.jpg{{{1233205}}}|||00-logistics-fear_not-cd-flac-2012.m3u{{{538}}}|||00-logistics-fear_not-cd-flac-2012.nfo{{{1607}}}|||00-logistics-fear_not-cd-flac-2012.sfv{{{688}}}|||01-logistics-fear_not.flac{{{38139451}}}|||02-logistics-timelapse.flac{{{39346037}}}|||03-logistics-2999_(wherever_you_go).flac{{{41491133}}}|||04-logistics-try_again.flac{{{32151567}}}|||05-logistics-we_are_one.flac{{{40778041}}}|||06-logistics-crystal_skies_(feat_nightshade_and_sarah_callander).flac{{{34544405}}}|||07-logistics-feels_so_good.flac{{{41363732}}}|||08-logistics-running_late.flac{{{16679269}}}|||09-logistics-early_again.flac{{{35373278}}}|||10-logistics-believe_in_me.flac{{{39495420}}}|||11-logistics-letting_go.flac{{{30846730}}}|||12-logistics-sendai_song.flac{{{35021141}}}|||13-logistics-over_and_out.flac{{{44621200}}}|||14-logistics-destination_unknown.flac{{{13189493}}}|||15-logistics-watching_the_world_go_by_(feat_alice_smith).flac{{{43472367}}}",
                "filePath": "Logistics-Fear_Not-CD-FLAC-2012-TaBoo",
                "userId": 567,
                "username": null
            },
            {
                "id": 30028889,
                "media": "CD",
                "format": "MP3",
                "encoding": "320",
                "remastered": false,
                "remasterYear": 0,
                "remasterTitle": "",
                "remasterRecordLabel": "",
                "remasterCatalogueNumber": "",
                "scene": false,
                "hasLog": false,
                "hasCue": false,
                "logScore": 0,
                "fileCount": 16,
                "size": 167593347,
                "seeders": 7,
                "leechers": 0,
                "snatched": 30,
                "freeTorrent": false,
                "time": "2012-05-02 07:39:30",
                "description": "",
                "fileList": "01 Logistics - Fear Not.mp3{{{11440094}}}|||02 Logistics - Timelapse.mp3{{{11931197}}}|||03 Logistics - 2999 (Wherever You Go).mp3{{{12767128}}}|||04 Logistics - Try Again.mp3{{{10123523}}}|||05 Logistics - We Are One.mp3{{{12664716}}}|||06 Logistics - Crystal Skies.mp3{{{10048294}}}|||07 Logistics - Feels So Good.mp3{{{11971952}}}|||08 Logistics - Running Late.mp3{{{6810155}}}|||09 Logistics - Early Again.mp3{{{11073337}}}|||10 Logistics - Believe In Me.mp3{{{12421259}}}|||11 Logistics - Letting Go.mp3{{{11697141}}}|||12 Logistics - Sendai Song.mp3{{{11732669}}}|||13 Logistics - Over And Out.mp3{{{15169339}}}|||14 Logistics - Destination Unknown.mp3{{{4976367}}}|||15 Logistics - Watching The World Go By.mp3{{{12469335}}}|||Cover.jpg{{{296841}}}",
                "filePath": "Logistics - Fear Not (NHS209CD) [CD] (2012)",
                "userId": 340871,
                "username": null
            }
        ]
    }
}
```

### Add Tag

Must use either API token or `$_POST['authkey']`

**URL:**
`ajax.php?action=add_tag&groupid=<Group Id>&tagname=<Tags>`

`ajax.php?action=addtag&groupid=<Group Id>&tagname=<Tags>`

**Arguments**

`groupid` - group id
`tagname` - comma separated list of tags to add to group

**Response format:**

```json
{
    "status": "success",
    "response": {
        "added": [
            "rock",
            "jazz"
        ],
        "rejected": []
    }
}
```

### Torrent

**URL:**
`ajax.php?action=torrent&id=<Torrent Id>`

**Arguments:**

`id` - torrent's id

`hash` - torrent's hash (must be uppercase)

**Response format:**

```json
{
    "status": "success",
    "response": {
        "group": {
            "wikiBody": "<strong>Best album!</strong>",
            "wikiBBcode": "[b]Best album![/b]",
            "wikiImage": "http://whatimg.com/i/ralpc.jpg",
            "proxyImage": "http://proxy.com/xyz/abc.jpg",
            "id": 72189681,
            "name": "Fear Not",
            "year": 2012,
            "recordLabel": "Hospital Records",
            "catalogueNumber": "NHS209CD",
            "releaseType": 1,
            "categoryId": 1,
            "categoryName": "Music",
            "time": "2012-05-02 07:39:30",
            "vanityHouse": false,
            "musicInfo": {
                "composers": [],
                "dj": [],
                "artists": [
                    {
                        "id": 1460,
                        "name": "Logistics"
                    }
                ],
                "with": [
                    {
                        "id": 25351,
                        "name": "Alice Smith"
                    },
                    {
                        "id": 44545,
                        "name": "Nightshade"
                    },
                    {
                        "id": 249446,
                        "name": "Sarah Callander"
                    }
                ],
                "conductor": [],
                "remixedBy": [],
                "producer": []
            }
        },
        "torrent": {
            "id": 29991962,
            "media": "CD",
            "format": "FLAC",
            "encoding": "Lossless",
            "remastered": false,
            "remasterYear": 0,
            "remasterTitle": "",
            "remasterRecordLabel": "",
            "remasterCatalogueNumber": "",
            "scene": true,
            "hasLog": false,
            "hasCue": false,
            "logScore": 0,
            "fileCount": 19,
            "size": 527749302,
            "seeders": 20,
            "leechers": 0,
            "snatched": 55,
            "freeTorrent": false,
            "time": "2012-04-14 15:57:00",
            "description": "[URL=http://whatimg.com/viewer.php?file=yvpsp.jpg][IMG]http://whatimg.com/i/yvpsp_thumb.jpg[/IMG][/URL]",
            "fileList": "00-logistics-fear_not-cd-flac-2012.jpg{{{1233205}}}|||00-logistics-fear_not-cd-flac-2012.m3u{{{538}}}|||00-logistics-fear_not-cd-flac-2012.nfo{{{1607}}}|||00-logistics-fear_not-cd-flac-2012.sfv{{{688}}}|||01-logistics-fear_not.flac{{{38139451}}}|||02-logistics-timelapse.flac{{{39346037}}}|||03-logistics-2999_(wherever_you_go).flac{{{41491133}}}|||04-logistics-try_again.flac{{{32151567}}}|||05-logistics-we_are_one.flac{{{40778041}}}|||06-logistics-crystal_skies_(feat_nightshade_and_sarah_callander).flac{{{34544405}}}|||07-logistics-feels_so_good.flac{{{41363732}}}|||08-logistics-running_late.flac{{{16679269}}}|||09-logistics-early_again.flac{{{35373278}}}|||10-logistics-believe_in_me.flac{{{39495420}}}|||11-logistics-letting_go.flac{{{30846730}}}|||12-logistics-sendai_song.flac{{{35021141}}}|||13-logistics-over_and_out.flac{{{44621200}}}|||14-logistics-destination_unknown.flac{{{13189493}}}|||15-logistics-watching_the_world_go_by_(feat_alice_smith).flac{{{43472367}}}",
            "filePath": "Logistics-Fear_Not-CD-FLAC-2012-TaBoo",
            "userId": 567,
            "username": null
        }
    }
}
```

## Upload

__NOTE__: Requires using the API token or authkey

**URL:**
`ajax.php?action=upload`

**Arguments:**

__NOTE__: Use `0` and `1` for boolean fields. Omitting the field is the same as setting it to `0`.

__NOTE__: If using `groupid`, may leave group details (e.g. `title`, `year`, etc.) out.

`file_input` - (file) .torrent file contents

`groupid` - (int) torrent groupID (ie album) this belongs to

`type` - (int) Category to use (0 - Music, 1 - Applications, etc., see classes/config.template.php for categories)

`artists[]` - (str) name of artist, provide multiple time per artist

`importance[]` - (int) index of artist type (Main, Guest, Composer, etc., see classes/config.template.php for artist types), provide multiple times per entry in `artist`

`title` - (str) Album title

`year` - (int) Album "Initial Year"

`record_label` - (str) Album record label

`releasetype` - (int) index of release type (Album, Soundtrack, EP, etc., see classes/config.template.php for release types)

`unknown` - (bool) Unknown Release

`remaster` - (bool) Is a remaster or not

`remaster_year` - (int) Edition year

`remaster_title` - (str) Edition title

`remaster_record_label` - (str) Edition record label

`remaster_catalogue_number` - (str) Edition catalog number

`scene` - (bool) is this a scene release?

`media` - (str) CD, WEB, DVD, etc.

`format` - (str) MP3, FLAC, etc

`bitrate` - (str) 192, Lossless, Other, etc

`other_bitrate` - (str) bitrate if bitrate is Other

`vbr - (bool)` other_bitrate is VBR

`logfiles[]` - (files) ripping log files

`extra_file_#` - (file) extra .torrent file contents, # is 1 to 5

`extra_format[]` - (str) extra torrent format

`extra_bitrate[]` - (str) extra torrent bitrate

`extra_release_desc[]` - (str) extra torrent release description

`vanity_house` - (bool) is this a Vanity House release?

`tags` - (str) comma separated list of tags for album

`image` - (str) link to album art

`album_desc` - (str) Album description

`release_desc` - (str) Release (torrent) description

`desc` - (str) Description for non-music torrents

`requestid` - (int) requestID being filled

**Response format:**

```json
{
    "status": "success",
    "response": {
        "groupId": 384,
        "torrentId": 526,
        "private": true,
        "source": true,
        "fillRequest": {
            "requestId": 1,
            "torrentId": 526,
            "fillerId": 1,
            "fillerName": "hermes",
            "bounty": 1232452
        },
        "warnings": [
            "non-critical html error messages"
        ]
    }
}
```

__NOTE__: If `private` or `source` is `false`, then you will need to [download](#download) the torrent file.

__NOTE__: If the provided `requestid` is invalid, then `fillRequest` in the response will have a key `error` that will designate what went wrong.

## Download

__NOTE__: Requires using the API token

**URL:**
`ajax.php?action=download&id=<Torrent Id>`

**Arguments:**

`id` - torrent id

`usetoken` - use FL token to download torrent (default: false)

`ssl` - force https (optional, defaults to user setting)

**Response format:**

Either a raw BEncoded torrent file with `application/x-bittorrent` content-type or regular JSON error format if invalid parameters.

### Add Log

**URL:**
`ajax.php?action=add_log&id=<Torrent ID>`

__Note__: Must be the uploader of the torrent or moderator to add logs

**GET Arguments:**

`id` - ID of torrent to add logs to

**POST Arguments:**

`logfiles[]` - (files) ripping log files

**Response format:**

```json
{
  "status": "success",
  "response": {
    "torrentId": 1,
    "score": 59,
    "checksum": "0",
    "logcheckerVersion": "0.11.0",
    "logSummaries": [
      {
        "score": 59,
        "checksum": "checksum_missing",
        "ripper": "EAC",
        "ripperVersion": "",
        "language": "en",
        "details": [
            "EAC version older than 0.99 (-30 points)",
            "Could not verify null samples",
            "Could not verify gap handling (-10 points)",
            "Could not verify id3 tag setting (-1 point)"
        ]
      }
    ]
  },
  "info": {
    "source": "Gazelle Dev",
    "version": 1
  }
}
```

## Logchecker

**URL:**
`ajax.php?action=logchecker`

**POST Arguments:**

__Note__: Only need to provide one of the following.

* `log` - uploaded log file through `multipart/form-data`
* `pastelog` - log submitted via regular `$_POST`

**Response Format**

```json
{
    "status": "success",
    "response": {
        "ripper": "EAC",
        "ripperVersion": "1.0 beta 3",
        "language": "en",
        "score": 59,
        "checksum": "checksum_invalid",
        "issues": [
            "Could not verify gap handling (-10 points)",
            "Could not verify id3 tag setting (-1 point)",
            "Range rip detected (-30 points)"
        ]
    }
}
```

__NOTE__: `checksum` field can have the following values: `checksum_ok`, `checksum_invalid`, `checksum_missing`

## Requests

### Request

**URL:**
`ajax.php?action=request&id=<Request Id>`

**Arguments:**

`id` - request id

`page` - page of the comments to display (default: last page)

**Response format:**

```json
{
    "status": "success",
    "response": {
        "requestId": 80983,
        "requestorId": 75670,
        "requestorName": "brontosaurus",
        "requestTax": 0.1,
        "timeAdded": "2010-01-08 03:12:39",
        "canEdit": false,
        "canVote": true,
        "minimumVote": 20971520,
        "voteCount": 765,
        "lastVote": "2012-08-08 20:37:24",
        "topContributors": [
            {
                "userId": 75670,
                "userName": "brontosaurus",
                "bounty": 1254160859136
            },
            // ...
        ],
        "totalBounty": 1489901312461,
        "categoryId": 1,
        "categoryName": "Music",
        "title": "4th Studio Album",
        "year": 2012,
        "image": "",
        "description": "This request is for a proper rip to FLAC at 24 bits \/ 96 kHz of the <br \/>\r\n4th Studio Album by Daft Punk<br \/>\r\n<br \/>\r\n<strong>A USB turntable rip does NOT suffice.<\/strong>",
        "musicInfo": {
            "composers": [],
            "dj": [],
            "artists": [{
                "id": 431,
                "name": "Daft Punk"
            }],
            "with": [],
            "conductor": [],
            "remixedBy": [],
            "producer": []
        },
        "catalogueNumber": "",
        "releaseType": 0,
        "releaseName": "Unknown",
        "bitrateList": "1",
        "formatList": "24bit Lossless",
        "mediaList": "FLAC",
        "logCue": "Vinyl",
        "isFilled": false,
        "fillerId": 0,
        "fillerName": "0",
        "torrentId": 0,
        "timeFilled": "0",
        "tags": ["electronic", "house", "french"],
        "comments": [
            {
                "postId": 63934,
                "authorId": 209372,
                "name": "verysofttoiletpaper",
                "donor": true,
                "warned": false,
                "enabled": true,
                "class": "Member",
                "addedTime": "2012-07-10 09:02:34",
                "avatar": "http:\/\/majastevanovich.files.wordpress.com\/2009\/10\/a20toilet20paper.jpg",
                "comment": "Can someone explain what is the attractiveness of a vinyl rip as opposed to a CD rip? CD should be direct from the source with no conversions, while on vinyl is has to be converted to analog and digital again.. isn't it loosing fidelity?",
                "editedUserId": 0,
                "editedUsername": "",
                "editedTime": ""
            },
            // ...
        ],
        "commentPage": 18,
        "commentPages": 18
    }
}
```

### Request Fill

**URL:**
`ajax.php?action=request_fill&requestid=<Request Id>&torrentid=<Torrent Id>`

`ajax.php?action=requestfill&requestid=<Request Id>&torrentid=<Torrent Id>`

**Arguments:**

`requestid` - Request Id

`torrentid` - Torrent Id

`link` - Permalink to torrent on site

`user` - Username to use for request fill (mod+ only)

__Note__: Either `torrentid` or `link` must be used

**Response format:**

```json
{
    "status": "success",
    "response": {
        "requestId": 1,
        "torrentId": 22,
        "fillerId": 1,
        "fillerName": "hermes",
        "bounty": 1232452
    }
}
```

## Collages

**URL:**
`ajax.php?action=collage&id=<Collage Id>`

**Arguments:**

`id` - collage's id
`page` - page number for torrent groups (default: 1)

**Response format:**

version: 3

```json
{
    "status": "success",
    "response": {
      "id": 13657,
      "name": "The Five Star Collection",
      "description": "The Five (5) Star Collection was a Compass Productions series made in cooperation with several major labels&apos; special markets divisions, including Sony Music Custom Marketing Group, Universal Music Special Markets and EMI Music Special Markets.<br />\r\n<br />\r\n<a rel=\"noreferrer\" target=\"_blank\" href=\"https://www.discogs.com/label/1127838-COLLECTION\">https://www.discogs.com/label/1127838-COLLECTION</a>",
      "description_raw": "The Five (5) Star Collection was a Compass Productions series made in cooperation with several major labels' special markets divisions, including Sony Music Custom Marketing Group, Universal Music Special Markets and EMI Music Special Markets.\r\n\r\nhttps://www.discogs.com/label/1127838-COLLECTION",
      "creatorID": 1212,
      "deleted": false,
      "collageCategoryID": 9,
      "collageCategoryName": "Series",
      "locked": false,
      "maxGroups": 0,
      "maxGroupsPerUser": 0,
      "hasBookmarked": false,
      "subscriberCount": 0,
      "torrentGroupIDList": [
        739530,
        648671
      ],
      "pages": 1,
      // for torrent collage:
      "torrentgroups": [
         // array of torrent groups as returned by action=torrentgroup
      ],
      // for artist collage:
      "artists": [
        {
          "id": 70362,
          "name": "Drago Mlinarec",
          "image": "https://ptpimg.me/gsotu8.jpg"
        },
        {
          "id": 812898,
          "name": "Grupa Marina Škrgatića",
          "image": "https://ptpimg.me/tpo6n0.jpg"
        }
      ]
    }
}
```

## Notifications

**URL:**
`ajax.php?action=notifications&page=<Page>`

**Arguments:**

`page` - page number to display (default: 1)

**Response format:**

```json
{
    "status": "success",
    "response": {
        "currentPages": 1,
        "pages": 105,
        "numNew": 0,
        "results": [
            {
                "torrentId": 30194383,
                "groupId": 71944561,
                "groupName": "You Are a Tourist",
                "groupCategoryId": 1,
                "torrentTags": "alternative indie",
                "size": 12279586,
                "fileCount": 2,
                "format": "MP3",
                "encoding": "320",
                "media": "WEB",
                "scene": false,
                "groupYear": 2011,
                "remasterYear": 0,
                "remasterTitle": "",
                "snatched": 2,
                "seeders": 3,
                "leechers": 0,
                "notificationTime": "2012-08-08 21:24:15",
                "hasLog": false,
                "hasCue": false,
                "logScore": 0,
                "freeTorrent": false,
                "logInDb": false,
                "unread": false
            },
            // ...
        ]
    }
}

## Similar Artists

**URL:**
`ajax.php?action=similar_artists&id=<Artist ID>&limit=<Limit>`

**Arguments**

`id` - id of artist

`limit` - maximum number of results to return (fewer might be returned)

**Response format:**

```json
[
    {
        "id": 8307,
        "name": "Fairmont",
        "score": 200
    },
    {
        "id": 3693,
        "name": "Paul Kalkbrenner",
        "score": 200
    },
    {
        "id": 32479,
        "name": "Lopazz",
        "score": 200
    },
    {
        "id": 33783,
        "name": "Pawas",
        "score": 200
    },
    {
        "id": 1564,
        "name": "Dubfire",
        "score": 200
    }
]
```


## Announcements

**URL:**
`ajax.php?action=announcements`

**Response format:**

```json
{
    "status": "success",
    "response": {
        "announcements": [
            {
                "newsId":263,
                "title":"An update! A new forum, new features and more.",
                "body":"Much has happened recently!...",
                "newsTime":"2012-11-14 03:14:12"
            },
            // ...
        ],
        "blogPosts": []
    }
}
```

## Give FL Tokens

__NOTE__: Requires using the API token or using `$_POST['authkey']`

**URL:**
`ajax.php?action=give_fltokens`

**Arguments**

`id` - user id to send tokens to

`fltype` - type of fl tokens to give where:

* `fl-other-1`: 1 token
* `fl-other-4`: 5 tokens
* `fl-other-2`: 10 tokens
* `fl-other-3`: 50 tokens

`fltokens` - number of tokens to send, must be either 1, 5, 10, or 50

__NOTE__: Must use either `fltype` or `fltokens`, should not use both

**Response format:**

```json
{
    "status": "success",
    "response": "FL tokens sent"
}
```


## Unofficial projects that utilize the API
- Python - https://github.com/cohena/pygazelle
- Python - https://github.com/isaaczafuta/whatapi
- Java - https://github.com/Gwindow/WhatAPI
- Ruby - https://github.com/chasemgray/RubyGazelle
- Javascript - https://github.com/deoxxa/whatcd
- C# - https://github.com/frankston/WhatAPI
- PHP - https://github.com/GLaDOSDan/whatcd-php
- PHP - https://github.com/Jleagle/php-gazelle
- Go - https://github.com/kdvh/whatapi
- Scala - https://github.com/bkkcanuck/askwhat
