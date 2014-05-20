<?php
/*

Name: Xmail Professional
Version: 2.3 Excalibur
Description: Send email the right way so it does not get flagged as SPAM. Most servers use a diffrent IP address to send email from then the IP of your domain and thus your emails get into SPAM folders or not att all in some cases of Yahoo! and MSN. This will send emails from your domain IP address. It might take 1-2 seconds more to send it but it is worth it.
Author: Vlad-Marian MARIAN
Author URI: http://uk.linkedin.com/in/transilvlad/
License: CC BY-NC-SA

  USAGE:

  Copyright (C) 2014

  Attribution-NonCommercial-ShareAlike
  CC BY-NC-SA

  This license lets others remix, tweak,
  and build upon this work non-commercially,
  as long as they credit the author and license
  their new creations under the identical terms.

*/

  error_reporting(E_ALL & ~E_NOTICE);

  class Xmail{

    // LOG VAR
    public $log = Array();

    // NEW LINE
    private $line = "\r\n";

    // ATTACHED FILES
    public $files = Array();

    // CONFIG GENERAL
    private $tpl = "";
    private $mode = "mail";
    private $safe = true;

    function setTPL($value) { if($value != "") $this->tpl = $value; }
    function setMODE($value) { if($value != "") $this->mode = $value; }

    // CONFIG SMTP
    private $smtp_host = "localhost";
    private $smtp_port = "25";
    private $smtp_username = "";
    private $smtp_password = "";

    function setSmtpHost($value) { if($value != "") $this->smtp_host = $value; }
    function setSmtpPort($value) { if($value != "") $this->smtp_port = $value; }
    function setSmtpUser($value) { if($value != "") $this->smtp_username = $value; }
    function setSmtpPass($value) { if($value != "") $this->smtp_password = $value; }

    // CONFIG SOCKET
    private $from = "xmail@localhost"; // sender email address
    private $host = "localhost"; // your domain name here
    private $port = "25"; // it is always 25 but i think it's best to have this for tests when developper pc has port 25 blocked and server has alternate port [i use 26 cause 25 is locked for anti SPAM by ISP]
    private $time = "30"; // timeout [time short :D]
    private $test = false; // test mode, does not send the email but you can see the log up to the point of sending email, good to check email addresses if valid or server if black-listed

    function setFrom($value) { if($value != "") $this->from = $value; }
    function setHost($value) { if($value != "") $this->host = $value; }
    function setPort($value) { if($value != "") $this->port = $value; }
    function setTime($value) { if($value != "") $this->time = $value; }
    function setTest($value) { if($value != "") $this->test = $value; }
    function setSafe($value) { if($value == True) $this->safe = true; else $this->safe = false; }

    // MAIN FUNCTION
    function mail($to, $subject, $msg, $headers, $attachments = NULL) {

      // MESSAGE HTML
      $msg = str_replace("\'","'",$msg);
      $msg = str_replace('\"','"',$msg);

      // Use template if case
      if(is_file($this->tpl)){
        $html = implode("", file($this->tpl));
        $html = str_replace("{MESSAGE}", $msg, $html);
      }else
       $html = $msg;

      $boundary1 = '-----='.md5(uniqid(rand()));
      $boundary2 = '-----='.md5(uniqid(rand()));

      $message .= "\r\nThis is a multi-part message in MIME format.\r\n\r\n";
      $message .= "--".$boundary1."\r\n";
      $message .= "Content-Type: multipart/alternative;\r\n      boundary=\"$boundary2\"\r\n\r\n";

      // MESSAGE TEXT
      $message .= "--".$boundary2."\r\n";
      $message .= "Content-Type: text/plain;\r\n      charset=\"UTF-8\"\r\n";
      $message .= "Content-Transfer-Encoding: 7bit\r\n";
      $message .= strip_tags($msg) . "\r\n";
      $message .= "\r\n\r\n";

      // MESSAGE HTML
      $message .= "--".$boundary2."\r\n";
      $message .= "Content-Type: text/html;\r\n      charset=\"UTF-8\"\r\n";
      $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
      $encoded  = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\">\r\n";
      $encoded .= "<html>\r\n";
      $encoded .= "<body>\r\n";
      $encoded .= $html . "\r\n";
      $encoded .= "</body>\r\n";
      $encoded .= "</html>\r\n\r\n";
      $message .= quoted_printable_encode($encoded);
      $message .= "--".$boundary2."--\r\n\r\n";

      if(is_array($attachments)) {
        foreach($attachments AS $file_url) {
          if(is_file($file_url)) {
            $file_name = pathinfo($file_url, PATHINFO_BASENAME);
            $file_type = $this->find_mime(pathinfo($file_url, PATHINFO_EXTENSION));

            // ATTACHMENT
            $message .= "--".$boundary1."\r\n";
            $message .= "Content-Type: ".$file_type.";\r\n      name=\"$file_name\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment;\r\n      filename=\"$file_name\"\r\n\r\n";

            $fp = fopen($file_url, 'r');
            do {
              $data = fread($fp, 8192);
              if (strlen($data) == 0) break;
              $content .= $data;
            }
            while (true);
            $content_encode = chunk_split(base64_encode($content));
            $message .= $content_encode."\r\n\r\n";
            $content = '';
            unset($content);

          }
        }
      }
      $message .= "--".$boundary1."--\r\n\r\n";

      $headers .= "MIME-Version: 1.0\r\n";
      $headers .= "Content-Type: multipart/mixed;\r\n      boundary=\"$boundary1\"\r\n";

      if($this->mode == "smtp" || $this->mode == "mx")
       return $this->sokmail($to, $subject, $message, $headers);
      else {
        if($this->safe && mail($to, $subject, $message, $headers)) return true;
        return false;
      }
    }

    // send mail directly to destination MX server
    private function sokmail($to, $subject, $message, $headers) {
      // get server based on mode
      if($this->mode == "mx") {
        list($user, $domain) = explode("@", $to);
        $mxips = $this->get_rand_mx_ip($domain);
        $server = $mxips['A'][0];
      }
      else
       $server = $this->smtp_host;

      // open socket
      $socket = @fsockopen($server, $this->port, $errno, $errstr, $this->time);
      if(empty($socket)) {
        $this->log["ERROR"] = "Couldn't connect to server.";
        $this->log["ERRNO"] = "101";
        return false;
      }
      if($this->parse_response($socket, 220, "SOCKET") != 220) { fclose($socket); return false; }

      // say HELO to our little friend
      fputs($socket, "EHLO " . $this->host . $this->line);
      if($this->parse_response($socket, 250, "HELO") != 250) { fclose($socket); return false; }

      // if SMTP
      if($this->mode == "smtp" && !empty($this->smtp_username) && !empty($this->smtp_password) ) {
        // start login
        fputs($socket, "AUTH LOGIN" . $this->line);
        if($this->parse_response($socket, 334, "AUTH LOGIN") != 334) { fclose($socket); return false; }

        fputs($socket, base64_encode($this->smtp_username) . $this->line);
        if($this->parse_response($socket, 334, "USERNAME") != 334) { fclose($socket); return false; }

        fputs($socket, base64_encode($this->smtp_password) . $this->line);
        if($this->parse_response($socket, 235, "PASSWORD") != 235) { fclose($socket); return false; }
      }

      // email from
      fputs($socket, "MAIL FROM: <" . $this->from . ">" . $this->line);
      if($this->parse_response($socket, 250, "MAIL FROM") != 250) { fclose($socket); return false; }

      // email to
      fputs($socket, "RCPT TO: <" . $to . ">" . $this->line);
      if($this->parse_response($socket, 250, "RCPT TO") != 250) { fclose($socket); return false; }

      // check for test mode
      if($this->test != true) {

        // send data start command
        fputs($socket, "DATA" . $this->line);
        if($this->parse_response($socket, 354, "DATA") != 354) { fclose($socket); return false; }

        // make the deposit :)
        fputs($socket, "Subject: " . $subject . $this->line);
        fputs($socket, "To: " . $to . $this->line);
        fputs($socket, $headers . $this->line);
        fputs($socket, $message . $this->line);
        fputs($socket, "." . $this->line); // this line sends a dot to mark the end of message
        if($this->parse_response($socket, 250, ".") != 250) { fclose($socket); return false; }
      }

      // say goodbye
      fputs($socket,"QUIT" . $this->line);
      $this->parse_response($socket, 221, "QUIT");
      fclose($socket);

      return true;
    }

    // parse server responces for above function
    private function parse_response($socket, $expected, $cmd) {
      $response = '';
      $this->log[$cmd] = "";
      while (substr($response, 3, 1) != ' ') {
        if(!($response = fgets($socket, 256))) {
          $this->log["ERROR"] = "Couldn't get mail server response codes.";
          $this->log["ERRNO"] = "102";
        }
        else $this->log[$cmd] .= $response;
        // for security we break the loop after 10 cause this should not happen ever
        $i++;
        if($i == 10) return false;
      }

      // shows an error if expected code not received
      if(substr($response, 0, 3) != $expected) {
        $this->log["ERROR"] = "Ran into problems sending Mail. Received: " . substr($response, 0, 3) . ".. but expected: " . $expected;
        $this->log["ERRNO"] = substr($response, 0, 3);
      }

      // access denied..quit
      if(substr($response, 0, 3) == 451) {
        $this->log["ERROR"] = "Server declined access. Quitting.";
        $this->log["ERRNO"] = "451";
      }

      return substr($response, 0, 3);
    }

    // get mx records and their IPs and randomize based on priority
    private function get_rand_mx($d) {
      getmxrr($d, $h, $w);
      
      if(!$h) {
        $h = Array($d);
        $w = Array(0);
      }

      $i = Array();
      $m = Array();
      foreach($h AS $k => $v) {
        if(empty($m[$w[$k]]))
         $m[$w[$k]] = Array();

        $m[$w[$k]][] = $v;
        $i[$v]['A'] = dns_get_record($v, DNS_A);
        $i[$v]['AAAA'] = dns_get_record($v, DNS_AAAA);
      }
      ksort($m);
      foreach($m AS $k => $v) {
        shuffle($m[$k]);
      }
      foreach($i AS $k => $v) {
        shuffle($i[$k]['A']);
        shuffle($i[$k]['AAAA']);
      }
      return Array('mx' => $m, 'ip' => $i);
    }

    // prepare IPs as a simpel list
    private function get_rand_mx_ip($d) {
      $a = $this->get_rand_mx($d);

      $r = Array('A' => Array(), 'AAAA' => Array());
      foreach($a['mx'] AS $m) {
        foreach($m AS $n) {
          foreach($a['ip'][$n]['A'] AS $i) {
            $r['A'][] = $i['ip'];
          }
          foreach($a['ip'][$n]['AAAA'] AS $i) {
            $r['AAAA'][] = $i['ipv6'];
          }
        }
      }
      return $r;
    }

    // get mime type for extension
    private function find_mime($ext) {
      // create mimetypes array
      $mimetypes = $this->mime_array();

      // return mime type for extension
      if (isset($mimetypes[$ext])) {
        return $mimetypes[$ext];
      // if the extension wasn't found return octet-stream
      } else {
        return 'application/octet-stream';
      }
    }

    // known mime types cause PHP might not have support
    private function mime_array() {
      return array(
        "ez" => "application/andrew-inset",
        "hqx" => "application/mac-binhex40",
        "cpt" => "application/mac-compactpro",
        "doc" => "application/msword",
        "bin" => "application/octet-stream",
        "dms" => "application/octet-stream",
        "lha" => "application/octet-stream",
        "lzh" => "application/octet-stream",
        "exe" => "application/octet-stream",
        "class" => "application/octet-stream",
        "so" => "application/octet-stream",
        "dll" => "application/octet-stream",
        "oda" => "application/oda",
        "pdf" => "application/pdf",
        "ai" => "application/postscript",
        "eps" => "application/postscript",
        "ps" => "application/postscript",
        "smi" => "application/smil",
        "smil" => "application/smil",
        "wbxml" => "application/vnd.wap.wbxml",
        "wmlc" => "application/vnd.wap.wmlc",
        "wmlsc" => "application/vnd.wap.wmlscriptc",
        "bcpio" => "application/x-bcpio",
        "vcd" => "application/x-cdlink",
        "pgn" => "application/x-chess-pgn",
        "cpio" => "application/x-cpio",
        "csh" => "application/x-csh",
        "dcr" => "application/x-director",
        "dir" => "application/x-director",
        "dxr" => "application/x-director",
        "dvi" => "application/x-dvi",
        "spl" => "application/x-futuresplash",
        "gtar" => "application/x-gtar",
        "hdf" => "application/x-hdf",
        "js" => "application/x-javascript",
        "skp" => "application/x-koan",
        "skd" => "application/x-koan",
        "skt" => "application/x-koan",
        "skm" => "application/x-koan",
        "latex" => "application/x-latex",
        "nc" => "application/x-netcdf",
        "cdf" => "application/x-netcdf",
        "sh" => "application/x-sh",
        "shar" => "application/x-shar",
        "swf" => "application/x-shockwave-flash",
        "sit" => "application/x-stuffit",
        "sv4cpio" => "application/x-sv4cpio",
        "sv4crc" => "application/x-sv4crc",
        "tar" => "application/x-tar",
        "tcl" => "application/x-tcl",
        "tex" => "application/x-tex",
        "texinfo" => "application/x-texinfo",
        "texi" => "application/x-texinfo",
        "t" => "application/x-troff",
        "tr" => "application/x-troff",
        "roff" => "application/x-troff",
        "man" => "application/x-troff-man",
        "me" => "application/x-troff-me",
        "ms" => "application/x-troff-ms",
        "ustar" => "application/x-ustar",
        "src" => "application/x-wais-source",
        "xhtml" => "application/xhtml+xml",
        "xht" => "application/xhtml+xml",
        "zip" => "application/zip",
        "au" => "audio/basic",
        "snd" => "audio/basic",
        "mid" => "audio/midi",
        "midi" => "audio/midi",
        "kar" => "audio/midi",
        "mpga" => "audio/mpeg",
        "mp2" => "audio/mpeg",
        "mp3" => "audio/mpeg",
        "aif" => "audio/x-aiff",
        "aiff" => "audio/x-aiff",
        "aifc" => "audio/x-aiff",
        "m3u" => "audio/x-mpegurl",
        "ram" => "audio/x-pn-realaudio",
        "rm" => "audio/x-pn-realaudio",
        "rpm" => "audio/x-pn-realaudio-plugin",
        "ra" => "audio/x-realaudio",
        "wav" => "audio/x-wav",
        "pdb" => "chemical/x-pdb",
        "xyz" => "chemical/x-xyz",
        "bmp" => "image/bmp",
        "gif" => "image/gif",
        "ief" => "image/ief",
        "jpeg" => "image/jpeg",
        "jpg" => "image/jpeg",
        "jpe" => "image/jpeg",
        "png" => "image/png",
        "tiff" => "image/tiff",
        "tif" => "image/tif",
        "djvu" => "image/vnd.djvu",
        "djv" => "image/vnd.djvu",
        "wbmp" => "image/vnd.wap.wbmp",
        "ras" => "image/x-cmu-raster",
        "pnm" => "image/x-portable-anymap",
        "pbm" => "image/x-portable-bitmap",
        "pgm" => "image/x-portable-graymap",
        "ppm" => "image/x-portable-pixmap",
        "rgb" => "image/x-rgb",
        "xbm" => "image/x-xbitmap",
        "xpm" => "image/x-xpixmap",
        "xwd" => "image/x-windowdump",
        "igs" => "model/iges",
        "iges" => "model/iges",
        "msh" => "model/mesh",
        "mesh" => "model/mesh",
        "silo" => "model/mesh",
        "wrl" => "model/vrml",
        "vrml" => "model/vrml",
        "css" => "text/css",
        "html" => "text/html",
        "htm" => "text/html",
        "asc" => "text/plain",
        "txt" => "text/plain",
        "rtx" => "text/richtext",
        "rtf" => "text/rtf",
        "sgml" => "text/sgml",
        "sgm" => "text/sgml",
        "tsv" => "text/tab-seperated-values",
        "wml" => "text/vnd.wap.wml",
        "wmls" => "text/vnd.wap.wmlscript",
        "etx" => "text/x-setext",
        "xml" => "text/xml",
        "xsl" => "text/xml",
        "mpeg" => "video/mpeg",
        "mpg" => "video/mpeg",
        "mpe" => "video/mpeg",
        "qt" => "video/quicktime",
        "mov" => "video/quicktime",
        "mxu" => "video/vnd.mpegurl",
        "avi" => "video/x-msvideo",
        "movie" => "video/x-sgi-movie",
        "ice" => "x-conference-xcooltalk"
      );
    }
  }


  // PHP does not have getmxr function on windows so I built one
  function win_getmxrr($hostname, &$mxhosts, &$mxweight=false) {
    if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') return;
    if (!is_array ($mxhosts) ) $mxhosts = array();
    if (empty($hostname)) return;
    $exec='nslookup -type=MX '.escapeshellarg($hostname);
    @exec($exec, $output);
    if (empty($output)) return;
    $i=-1;
    foreach ($output as $line) {
      $i++;
      if (preg_match("/^$hostname\tMX preference = ([0-9]+), mail exchanger = (.+)$/i", $line, $parts)) {
        $mxweight[$i] = trim($parts[1]);
        $mxhosts[$i] = trim($parts[2]);
      }
      if (preg_match('/responsible mail addr = (.+)$/i', $line, $parts)) {
        $mxweight[$i] = $i;
        $mxhosts[$i] = trim($parts[1]);
      }
    }
    return ($i!=-1);
  }
  if (!function_exists('getmxrr')) {
    function getmxrr($hostname, &$mxhosts, &$mxweight=false) {
      return win_getmxrr($hostname, $mxhosts, $mxweight);
    }
  }


  // Handler function
  function xmail($to, $subject, $message, $headers="", $attachments=""){
    $xmail = new Xmail();
    $xmail->setTest(false); // ( true / false ) test without sending email see output
    $xmail->setSafe(true); // ( true / false ) pass the email to PHP's mail() if fail to deliver

    $xmail->setTPL('xmail.html'); // define a file to be used as a template if you want, use {MESSAGE} where the message should be inserted in the template
    $xmail->setMODE('mx'); // default is mail; options: mail,smtp,mx

    // MX setup if in 'mx' mode
    $xmail->setFrom('john.doe@example.com');
    $xmail->setHost($_SERVER['HTTP_HOST']);
    $xmail->setTime(5);

    // SMTP SETUP if in 'smtp' mode
    $xmail->setSmtpHost('example.com');
    $xmail->setSmtpPort(25);
    $xmail->setSmtpUser('username');
    $xmail->setSmtpPass('password');

    // from this point on you have to provide the same info as if you would use mail()
    if($headers == "") {
     $headers = "From: John Doe <john.doe@example.com>\r\n";
     $headers .= "Disposition-Notification-To: John Doe <john.doe@example.com>\r\n";  // this will request a read receipt to be sent back to you the sender
    }
    // !!! DO NOT ADD MIME VERSION OR CONTENT TYPE HEADERS !!!

    // send the email
    $xmail->mail($to, $subject, $message, $headers, $attachments);
    
    // return debug log
    return $xmail->log;
  }
