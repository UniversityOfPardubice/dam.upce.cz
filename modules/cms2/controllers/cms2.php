<?php

defined("SYSPATH") or die("No direct script access.");

class cms2_Controller extends Controller {

    public function send($id, $hash, $width, $height) {
        if ($hash === 'metadata.js') {
            return $this->metadata($id);
        }
        $hash = preg_replace('/.jpg$/', '', $hash);
        $key = ORM::factory('user_access_key')->where('user_id', '=', 2)->find();
        $hashOrig = md5($key->access_key . $id);
        if ($hash !== $hashOrig) {
            throw new Kohana_404_Exception();
        }
        $item = ORM::factory("item", $id);

        if ($width === 'thumb.jpg') {
            $inFile = $item->thumb_path();
            $width = 100;
        } else {
            if (module::is_active("keeporiginal") && module::get_var("downloadfullsize", "DownloadOriginalImage")) {
                $inFile = VARPATH . "original/" . str_replace(VARPATH . "albums/", "", $item->file_path());
                if (!file_exists($inFile)) {
                    $inFile = $item->file_path();
                }
            } else {
                $inFile = $item->file_path();
            }
        }
        if ($width === NULL) {
            $width = $item->width;
            $height = $item->height;
        } elseif ($height === NULL) {
            $width = intval($width);
            $height = round($width * $item->height / $item->width);
        } else {
            $height = intval($height);
        }

        if (!is_dir(VARPATH . "modules/cms2/" . $item->id)) {
            @mkdir(VARPATH . "modules/cms2/" . $item->id);
        }
        $outFile = VARPATH . "modules/cms2/" . $item->id . "/" . $width . "x" . $height . ".jpg";
        if (!is_file($outFile)) {
            gallery_graphics::resize($inFile, $outFile, array('width' => $width, 'height' => $height, 'master' => Image::NONE));
        }

        $cacheFolder = preg_replace('@^kohana_uri.*cms2/send/(.+)/.+\.jpg$@', '$1', $_SERVER['REDIRECT_QUERY_STRING']);
        $cacheFile = preg_replace('@^.*/([^/]+)$@', '$1', $_SERVER['REDIRECT_QUERY_STRING']);
        @mkdir(VARPATH . "modules/cms2/httpd/" . $cacheFolder, 0777, true);
        @copy($outFile, VARPATH . "modules/cms2/httpd/" . $cacheFolder . "/" . $cacheFile);

        expires::check(3600, filemtime($outFile)); //1h
        expires::set(3600, filemtime($outFile));
        download::send($outFile);
    }

    public function metadata($id) {
        $item = ORM::factory("item", $id);
        if (!$item->is_photo()) {
            throw new Kohana_404_Exception();
        }
        $all_custom = custom_fields::get_extra_data($item, true);
        $data = array(
            'name' => $item->description ? $item->description : $item->title
        );
        foreach ($all_custom as $custom) {
            $values = array();
            foreach ($custom['bits'] as $bit) {
                $values[] = $bit['value'];
            }
            $data[strtr($custom['name'], array(' ' => '_'))] = implode('; ', $values);
        }

        switch (Input::instance()->get("format", "json")) {
            case 'json':
                $ret = json_encode($data);
                header("Content-Type: text/javascript");
                @mkdir(VARPATH . "modules/cms2/httpd/" . $id, 0777, true);
                $f = fopen(VARPATH . "modules/cms2/httpd/" . $id . "/metadata.js", "w");
                fwrite($f, $ret);
                fclose($f);
                echo($ret);
                break;
            case "jsonp":
                if (!($callback = Input::instance()->get("callback", ""))) {
                    throw new Rest_Exception(
                    "Bad Request", 400, array("errors" => array("callback" => "missing")));
                }
                if (preg_match('/^[$A-Za-z_][0-9A-Za-z_]*$/', $callback) == 1) {
                    header("Content-type: application/javascript; charset=UTF-8");
                    print "$callback(" . json_encode($data) . ")";
                } else {
                    throw new Rest_Exception(
                    "Bad Request", 400, array("errors" => array("callback" => "invalid")));
                }
                break;
            case "xml":
                header("Content-Type: text/xml; charset=UTF-8");
                print xml::to_xml($data, array("response", "item"));
                break;
            default:
                throw new Rest_Exception("Bad Request", 400);
        }
    }

}
