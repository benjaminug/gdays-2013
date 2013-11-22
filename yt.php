<?php
$clientLibraryPath = 'ZendGdata-1.12.3/library';
$oldPath = set_include_path(get_include_path() . PATH_SEPARATOR . $clientLibraryPath);

/**
 * @see Zend_Loader
 */
require_once 'Zend/Loader.php';
Zend_Loader::loadClass('Zend_Gdata_YouTube');
Zend_Loader::loadClass('Zend_Gdata_AuthSub');
Zend_Loader::loadClass('Zend_Gdata_App_Exception');

/* Videos */

// Upload
function uploadVideo($videoTitle, $videoDescription, $videoCategory, $videoTags, $nextUrl = null){
	$httpClient = getAuthSubHttpClient();
    $youTubeService = new Zend_Gdata_YouTube($httpClient);
    $newVideoEntry = new Zend_Gdata_YouTube_VideoEntry();

    $newVideoEntry->setVideoTitle($videoTitle);
    $newVideoEntry->setVideoDescription($videoDescription);

    //make sure first character in category is capitalized
    $videoCategory = strtoupper(substr($videoCategory, 0, 1))
        . substr($videoCategory, 1);
    $newVideoEntry->setVideoCategory($videoCategory);

    // convert videoTags from whitespace separated into comma separated
    $videoTagsArray = explode(' ', trim($videoTags));
    $newVideoEntry->setVideoTags(implode(', ', $videoTagsArray));

    $tokenHandlerUrl = 'https://gdata.youtube.com/action/GetUploadToken';
    try {
        $tokenArray = $youTubeService->getFormUploadToken($newVideoEntry, $tokenHandlerUrl);
    } catch (Zend_Gdata_App_HttpException $httpException) {
        print 'ERROR ' . $httpException->getMessage();
        return;
    } catch (Zend_Gdata_App_Exception $e) {
        print 'ERROR - Could not retrieve token for syndicated upload. ' . $e->getMessage();
        return;
    }

    $tokenValue = $tokenArray['token'];
    $postUrl = $tokenArray['url'];

    // place to redirect user after upload
    if (!$nextUrl) {
        $nextUrl = $_SESSION['homeUrl'];
    }

    print <<< END
        <br /><form action="${postUrl}?nexturl=${nextUrl}"
        method="post" enctype="multipart/form-data">
        <input name="file" type="file"/>
        <input name="token" type="hidden" value="${tokenValue}"/>
        <input value="Upload Video File" type="submit" />
        </form>
END;
}
// Delete
function deleteVideo($videoId){
	$httpClient = getAuthSubHttpClient();
    $youTubeService = new Zend_Gdata_YouTube($httpClient);
    $feed = $youTubeService->getVideoFeed('https://gdata.youtube.com/feeds/users/default/uploads');
    $videoEntryToDelete = null;

    foreach($feed as $entry) {
        if ($entry->getVideoId() == $videoId) {
            $videoEntryToDelete = $entry;
            break;
        }
    }

    // check if videoEntryToUpdate was found
    if (!$videoEntryToDelete instanceof Zend_Gdata_YouTube_VideoEntry) {
        print 'ERROR - Could not find a video entry with id ' . $videoId . '<br />';
        return;
    }

    try {
        $httpResponse = $youTubeService->delete($videoEntryToDelete);
    } catch (Zend_Gdata_App_HttpException $httpException) {
        print $httpException->getMessage() . $httpException->getRawResponseBody();
        return;
    } catch (Zend_Gdata_App_Exception $e) {
        print 'ERROR - Could not delete video: '. $e->getMessage();
        return;
    }

    print "Video $videoId deleted successfully";
}

// Search
function findVideo($searchTerm, $startIndex=0, $maxResults=30){
	// create an unauthenticated service object
    $youTubeService = new Zend_Gdata_YouTube();
    $query = $youTubeService->newVideoQuery();
    $query->setQuery($searchTerm);
    $query->setStartIndex($startIndex);
    $query->setMaxResults($maxResults);

    $feed = $youTubeService->getVideoFeed($query);
    return $feed;
}

// List my videos
function listVideos(){
	$httpClient = getAuthSubHttpClient();
    $youTubeService = new Zend_Gdata_YouTube($httpClient);
    $feed = null;
    try {
        $feed = $youTubeService->getUserUploads('default');
    } catch (Zend_Gdata_App_HttpException $httpException) {
        print 'ERROR ' . $httpException->getMessage();
        return;
    } catch (Zend_Gdata_App_Exception $e) {
        print 'ERROR - Could not retrieve users video feed: ' . $e->getMessage();
        return;
    }
    return $feed;
}

// Update
function updateVideo($videoId, $newVideoTitle, $newVideoDescription, $newVideoCategory, $newVideoTags){
	$httpClient = getAuthSubHttpClient();
    $youTubeService = new Zend_Gdata_YouTube($httpClient);
    $feed = $youTubeService->getVideoFeed('https://gdata.youtube.com/feeds/users/default/uploads');
    $videoEntryToUpdate = null;

    foreach($feed as $entry) {
        if ($entry->getVideoId() == $videoId) {
            $videoEntryToUpdate = $entry;
            break;
        }
    }

    if (!$videoEntryToUpdate instanceof Zend_Gdata_YouTube_VideoEntry) {
        print 'ERROR - Could not find a video entry with id ' . $videoId;
        return;
    }

    try {
        $putUrl = $videoEntryToUpdate->getEditLink()->getHref();
    } catch (Zend_Gdata_App_Exception $e) {
        print 'ERROR - Could not obtain video entry\'s edit link';
        return;
    }

    $videoEntryToUpdate->setVideoTitle($newVideoTitle);
    $videoEntryToUpdate->setVideoDescription($newVideoDescription);
    $videoEntryToUpdate->setVideoCategory($newVideoCategory);

    // convert tags from space separated to comma separated
    $videoTagsArray = explode(' ', trim($newVideoTags));

    // strip out empty array elements
    foreach($videoTagsArray as $key => $value) {
        if (strlen($value) < 2) {
            unset($videoTagsArray[$key]);
        }
    }

    $videoEntryToUpdate->setVideoTags(implode(', ', $videoTagsArray));

    try {
        $updatedEntry = $youTubeService->updateEntry($videoEntryToUpdate, $putUrl);
    } catch (Zend_Gdata_App_HttpException $httpException) {
        print 'ERROR ' . $httpException->getMessage();
        return;
    } catch (Zend_Gdata_App_Exception $e) {
        print 'ERROR - Could not post video meta-data: ' . $e->getMessage();
        return;
    }
        print 'Entry updated successfully.';
}

function getTopRated(){
    $httpClient = getAuthSubHttpClient();
    $youTubeService = new Zend_Gdata_YouTube($httpClient);
    $query = $youTubeService->newVideoQuery();
    $videoFeed = $youTubeService->getTopRatedVideoFeed($query);
    return getTopRated();
}

function getTopRatedToday(){
    $httpClient = getAuthSubHttpClient();
    $youTubeService = new Zend_Gdata_YouTube($httpClient);
    $query = $youTubeService->newVideoQuery();
    $query->setTime('today');
    $videoFeed = $youTubeService->getTopRatedVideoFeed($query);
    return $videoFeed;
}

function getAuthSubHttpClient(){
	$httpClient = null;
    try {
        $httpClient = Zend_Gdata_AuthSub::getHttpClient("1/wTx46tt6xU1VpyoEo3n9uvZY9nk0vjxXh7NtDuXY9r8");
    } catch (Zend_Gdata_App_Exception $e) {
        print 'ERROR - Could not obtain authenticated Http client object. '
            . $e->getMessage();
        return;
    }
    $httpClient->setHeaders('X-GData-Key', 'key=AIzaSyDAG7Rm7mwQWuTq8qjKbdcN3f3uXf-GGNI');
    return $httpClient;
}

function printUserPlaylists($user){
    $httpClient = getAuthSubHttpClient();
    $yt = new Zend_Gdata_YouTube($httpClient);
    $playlistListFeed = $yt->getPlaylistListFeed($user);

    foreach ($playlistListFeed as $playlistEntry) {
        echo $playlistEntry->title->text . "<br>";
        echo $playlistEntry->description->text . "<br>";
        
        $feedUrl = $playlistEntry->getPlaylistVideoFeedUrl();
        echo $feedUrl . "<br>";
        echo "<hr>";
        /*
        $playlistVideoFeed = $yt->getPlaylistVideoFeed($feedUrl);
        foreach ($playlistVideoFeed as $videoEntry) {
            printVideoEntry($videoEntry);
        }
        echo "<hr>";
        break;
        */
    }
    //return $playlistListFeed;
}

function printVideoEntry($videoEntry) 
{
  // the videoEntry object contains many helper functions
  // that access the underlying mediaGroup object
  echo 'Video: ' . $videoEntry->getVideoTitle() . "<br>";
  echo 'Video ID: ' . $videoEntry->getVideoId() . "<br>";
  echo 'Updated: ' . $videoEntry->getUpdated() . "<br>";
  echo 'Description: ' . $videoEntry->getVideoDescription() . "<br>";
  echo 'Category: ' . $videoEntry->getVideoCategory() . "<br>";
  echo 'Tags: ' . implode(", ", $videoEntry->getVideoTags()) . "<br>";
  echo 'Watch page: ' . $videoEntry->getVideoWatchPageUrl() . "<br>";
  echo 'Flash Player Url: ' . $videoEntry->getFlashPlayerUrl() . "<br>";
  echo 'Duration: ' . $videoEntry->getVideoDuration() . "<br>";
  echo 'View count: ' . $videoEntry->getVideoViewCount() . "<br>";
  //pr($videoEntry->getVideoRatingInfo());
  echo 'Rating: ' . $videoEntry->getVideoRatingInfo()["average"] . "<br>";
  echo 'Geo Location: ' . $videoEntry->getVideoGeoLocation() . "<br>";
  echo 'Recorded on: ' . $videoEntry->getVideoRecorded() . "<br>";
  
  // see the paragraph above this function for more information on the 
  // 'mediaGroup' object. in the following code, we use the mediaGroup
  // object directly to retrieve its 'Mobile RSTP link' child
  foreach ($videoEntry->mediaGroup->content as $content) {
    if ($content->type === "video/3gpp") {
      echo 'Mobile RTSP link: ' . $content->url . "<br>";
    }
  }
  
  echo "Thumbnails:<br>";
  $videoThumbnails = $videoEntry->getVideoThumbnails();

  foreach($videoThumbnails as $videoThumbnail) {
    echo $videoThumbnail['time'] . ' - ' . $videoThumbnail['url'];
    echo ' height=' . $videoThumbnail['height'];
    echo ' width=' . $videoThumbnail['width'] . "<br>";
  }

  echo "<hr>";
}

function pr($obj){
    echo '<pre>';
    print_r($obj);
    echo '</pre>';    
}

$videos = printUserPlaylists("CollegeHumor");
exit();
foreach ($videos as $video) {
    printVideoEntry($video);
}
?>