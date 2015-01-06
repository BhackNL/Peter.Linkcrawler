<?php
header("Content-Type: text/plain");

// Please change the url below to the url of your main page.
$rootPage = "http://www.rug.nl";

// Please specify a path to save a text file containing the broken links.
$file = fopen("../../Desktop/Brokenlinks.txt", "x");

// We want to filter some links out based on specific keywords (e.g. "login?" or "?lang")
// You can write every keyword you want to use for the filter into this array.
// I only wrote some default keywords in here, but this array can be configured for your personal needs.
$filterKeys = array(
    "login?",
    "?lang",
    "javascript:",
    "!rss",
    "?print",
    ".pdf"
);

// Here we implement a history, so that checked links are not checked again.
$history = array();


class Page {
    var $ownUrl;
    var $linksOnPage;
    var $completeLinks;
    var $ownStatus;
    var $parentUrl;

    function Page($parent, $url, $goFurther) {
        global $history;
        global $rootPage;
        global $file;

        $this->parentUrl = $parent;
        $this->ownUrl = $url;
        $request = curl_init($url);
        curl_setopt_array($request, array(
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10
        ));

        $output = curl_exec($request);
        $this->ownStatus = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        // I first used the following alternative to cURL, but it needs the installation of the PECL extension from http://pecl.php.net/package/pecl_http
        // I decided on using cURL instead, because the installation of the PECL extension didn't quite work for me and might cause problems if this script is used a different machine.
        /*$request = new HttpRequest($url);
        $request->setOptions(array('redirect' => 8));
        $request->send();
        $this->ownStatus = $request->getResponseCode();*/

        echo ($this->parentUrl." ");
        echo ($this->ownUrl." ");
        echo ($this->ownStatus."\n");
        if ($this->ownStatus == 200 && $goFurther == true && KeyFilter($this->ownUrl)) {
            $matches = array();
            preg_match_all('/<a.+href="(.+)"/iU', $output, $matches);   // Reads out all links on the page

            if (!empty($matches)) {
                // Now we will streamline the links first to avoid multiple checks of the same page.
                array_walk($matches[1], "SlashStripper");                   // Erase the "/" at the end
                $woDup = array_unique($matches[1]);                         // Deletes duplicates
                $this->linksOnPage = array_filter($woDup, "LinkFilter"); // Delete # and links that redirect to the parent page.

                // Now we will write out the links in full. E.g. "/bibliotheek" becomes "http://www.rug.nl/bibliotheek"
                foreach ($this->linksOnPage as $link) {
                    if ($link[0] == "/") {
                        $this->completeLinks[] = $rootPage . $link;
                    } else {
                        $this->completeLinks[] = $link;
                    }
                }

                foreach ($this->completeLinks as $link) {
                    if (!in_array($link, $history)) {
                        $history[] = $link;
                        if (strpos($link, $rootPage) !== false) {
                            $page = new Page($this->ownUrl, $link, true);
                        } else {
                            $page = new Page($this->ownUrl, $link, false);
                        }
                    }
                }
            }
        } else if ($this->ownStatus != 200) {
            fwrite($file, $this->parentUrl . " " . $this->ownUrl . " " . $this->ownStatus . "\n");
        }
    }
}

$history[] = $rootPage;
$page = new Page($rootPage, $rootPage, true);

function SlashStripper(&$link) {
    $link = rtrim($link, "/");
}

function KeyFilter($link) {
    global $filterKeys;
    $i = 0;

    foreach ($filterKeys as $key) {
        if (strpos($link, $key) !== false) {$i++;}
    }

    return($i == 0 ? true : false);
}
//Test
function LinkFilter($link) {
    if ($link == "" || $link[0] == "#"){
        return false;
    } else {
        return true;
    }
}


?>