<?php

/**modo{classes}(Xmail)

  @(Description)
    Email delivery class that provides the basic requirements in order to send RFC compliant emails.

  @(Description)(1){note warn}
    Don't define the following headers: MIME-Version, Content-Type, Subject, To


  @(Syntax)
  @(Syntax)(1){code php}
object `Xmail` ( boolean ~$errors_on~ )


  @(Parameters)
  @(Parameters)($errors_on)
    Turns php error reportin to E_ALL.


  @(Examples)
  @(Examples)(1){info}
    `Example 1:` Complete usage example.

  @(Examples)(2){code php}
// Sender
$sender_name    = 'John Doe';
$sender_address = 'john.doe';
$sender_domain  = 'example.com';

// Instanciate
$xmail = new Xmail();

// Test mode
$xmail->setTest(False); // (Boolean: default False) Test without sending the data

// Delivery mode
$xmail->setMode('mx'); // (mail, smtp, mx: default mail) Choose mode of delivery.

// MX setup if in 'mx' mode
$xmail->setFrom($sender_address . '@' . $sender_domain);
$xmail->setHost($sender_domain);
$xmail->setTime(5);

// SMTP SETUP if in 'smtp' mode
$xmail->setSmtpHost($sender_domain);
$xmail->setSmtpPort(25);
$xmail->setSmtpUser('username');
$xmail->setSmtpPass('password');

// Generic From header
if(empty($headers))
 $headers = 'From: "' . $sender_name . '" <' . $sender_address . '@' . $sender_domain . ">\r\n";

// Compose
$xmail->compose($message, $headers, $attachments);

// Send
$xmail->mail($to, $subject, $message, $headers, $attachments);


  @(Changelog){list}
   (1.0) ~Initial release.~
   (1.01) ~Added SIZE transmision.~
   (1.02) ~Added redundancy to mail() if socket fails.~
   (1.03) ~Removed SIZE transmision cause most servers do not support it.~
   (1.04) ~Fixed headers received as array.~
   (1.05) ~Standardized line endings.~
   (1.06) ~Minor fixez.~
   (2.0) ~Enhanced SMTP protocol sending and added randomized MX selection.~
   (3.0) ~Split delivery and composing.~

*/

  /**
   * Xmail
   *
   * Email delivery class that provides the basic requirements in order to send RFC compliant emails.
   *
   * @filesource
   *
   * @category Email
   * @package \email
   * @subpackage \email\xmail
   *
   * @version 20150219
   * @since 20060628
   *
   * @author "Vlad Marian" <transilvlad@gmail.com>
   * @contributor Cristian Ciocău <cciocau@gmail.com>
   * @copyright Vlad Marian 2015
   * @license http://www.gnu.org/licenses/agpl.txt GPL v3
   * @link http://transilvlad.com Author Website
   *
   * @throws BadMethodCallException if called methods are not defined.
   * @throws InvalidArgumentException if provided arguments of public methods are invalid.
   *
   * @see mail()
   */
  final class Xmail {
    /**
     * Xmail::NAME
     *
     * Just a name I like giving this particular class.
     */
    const NAME = 'Dreadnought';

    /**
     * Xmail::VERSION
     *
     * Version number.
     */
    const VERSION = '3.0';

    /**
     * Xmail::$log
     *
     * Will hold the SMTP transaction log if any.
     */
    public $log = Array();

    /**
     * Xmail::$email
     *
     * Will hold the composed email code.
     */
    public $email = '';

    /**
     * Xmail::$line
     *
     * New Line character for ease of use.
     */
    private $line = "\r\n";

    /**
     * Xmail::$mode
     *
     * Email delivery mode.
     * Option are: mail, mx and smtp
     * mail - will use the internal php mail() function.
     * mx   - will deliver emails directly to receipient DNS MX server.
     * smtp - will use a user defined SMTP server to relay the email.
     */
    private $mode = 'mail';

    /**
     * Xmail::$smtp_host
     *
     * SMTP relay server.
     */
    private $smtp_host = 'localhost';

    /**
     * Xmail::$smtp_port
     *
     * SMTP relay server port.
     */
    private $smtp_port = 25;

    /**
     * Xmail::$smtp_user
     *
     * SMTP relay server username.
     */
    private $smtp_user = '';

    /**
     * Xmail::$smtp_pass
     *
     * SMTP relay server password.
     */
    private $smtp_pass = '';

    /**
     * Xmail::$from
     *
     * MX mode sender email address.
     */
    private $from = 'xmail@localhost';

    /**
     * Xmail::$host
     *
     * MX mode sender hostname.
     */
    private $host = 'localhost';

    /**
     * Xmail::$port
     *
     * MX mode port number of receipient server. This should normally be 25.
     */
    private $port = 25;

    /**
     * Xmail::$time
     *
     * MX mode timeout.
     */
    private $time = 30;

    /**
     * Xmail::$test
     *
     * MX or SMTP mode protocol test on or off.
     * If on (true) it will not do the DATA transmission and quit after RCPT.
     * A good way to test if there are issues up to that point.
     */
    private $test = False;


    /**
     * Xmail::__construct()
     *
     * Used to turn php error reportin to E_ALL.
     *
     * @param  boolean  $errors_on
     */
    public function __construct($errors_on = False) {
      if($errors_on) error_reporting(E_ALL);
    }


    /**
     * Xmail::__call()
     *
     * Magick method intercepting invokcations of undefined methods.
     * This is used as a handy way of controlling private variables.
     *
     * @syntax Xmail::setMode($value);     // Email delivery mode (mail / mx / smtp).
     * @syntax Xmail::setSmtpHost($value); // SMTP relay server.
     * @syntax Xmail::setSmtpPort($value); // SMTP relay server port.
     * @syntax Xmail::setSmtpUser($value); // SMTP relay server username.
     * @syntax Xmail::setSmtpPass($value); // SMTP relay server password.
     * @syntax Xmail::setFrom($value);     // MX mode sender email address.
     * @syntax Xmail::setHost($value);     // MX mode sender hostname.
     * @syntax Xmail::setPort($value);     // MX mode port number of receipient server. This should normally be 25.
     * @syntax Xmail::setTime($value);     // MX mode timeout.
     * @syntax Xmail::setTest($value);     // MX or SMTP mode protocol test on or off.
     * @param  mixed   $arguments Array of arguments.
     */
    public function __call($name, $arguments) {
      // Convert upper camel case to underscored.
      $variable = strtolower(implode('_', array_filter(preg_split('/(?=[A-Z])/', preg_replace('/^set/', '', $name)))));

      // Argument to array.
      $argument = is_array($arguments) ? $arguments[0] : $arguments;

      // Throw exception if variable not found.
      if(!isset($this->{$variable}))
        throw new BadMethodCallException('Method ' . $name . ' is not available.');

      // Throw exceptions if argument type invalid.
      if(in_array($variable, Array('mode', 'smtp_host', 'smtp_user', 'smtp_pass', 'from', 'host')) && !is_string($argument))
        throw new InvalidArgumentException('$' . $variable . ' value is not of string type.');

      if(in_array($variable, Array('smtp_port', 'port', 'time')) && !is_int($argument))
        throw new InvalidArgumentException('$' . $variable . ' value is not of integer type.');

      if(in_array($variable, Array('test')) && !is_bool($argument))
        throw new InvalidArgumentException('$' . $variable . ' value is not of boolean type.');

      // Set variable value.
      $this->{$variable} = $argument;
    }


    /**
     * Xmail::mail()
     *
     * Email delivery method designed to be called similarly to the PHP mail().
     *
     * @syntax Xmail::mail($to = Null, $subject = Null, $message = Null, $headers = '', $attachments = Array());
     * @param  string  $to           Receipient email address.
     * @param  string  $subject      Subject of the email.
     * @param  string  $message      Email message in HTML format.
     * @param  string  $headers      Email headers except MIME-Version, Content-Type, Subject, To.
     * @param  array   $attachments  Array of paths to files to attach.
     * @return boolean Result of email delivery.
     */
    public function mail($to = Null, $subject = '', $message = '', $headers = '', $attachments = Array()) {
      // Throw exceptions if variables invalid.
      if(!filter_var($to, FILTER_VALIDATE_EMAIL))
        throw new InvalidArgumentException('Argument $to is invalid.');

      if(!is_string($subject))
        throw new InvalidArgumentException('Argument $subject is invalid.');

      if(!is_string($message))
        throw new InvalidArgumentException('Argument $message is invalid.');

      if(!is_string($headers))
        throw new InvalidArgumentException('Argument $headers is invalid.');

      if(!is_array($attachments))
        throw new InvalidArgumentException('Argument $attachments is invalid.');

      // Call composer
      if(empty($this->email))
       $this->compose($message, $headers, $attachments);

      // Get email parts
      list($headers, $message) = $this->email;

      if($this->mode == 'smtp' || $this->mode == 'mx')
       $ret = $this->smtp($to, $subject, $message, $headers);

      else
       $ret = !empty($this->from) ? mail($to, $subject, $message, $headers, '-f ' . $this->from) : mail($to, $subject, $message, $headers);

      return $ret;
    }


    /**
     * Xmail::compose()
     *
     * HTML email composing method.
     *
     * @syntax Xmail::compose($message = '', $headers = '', $attachments = Array());
     * @param  string  $message      Email message in HTML format.
     * @param  string  $headers      Email headers except MIME-Version, Content-Type, Subject, To.
     * @param  array   $attachments  Array of paths to files to attach.
     */
    public function compose($message = '', $headers = '', $attachments = Array()) {
      // Throw exceptions if variables invalid.
      if(!is_string($message))
        throw new InvalidArgumentException('Argument $message is invalid.');

      if(!is_string($headers))
        throw new InvalidArgumentException('Argument $headers is invalid.');

      if(!is_array($attachments))
        throw new InvalidArgumentException('Argument $attachments is invalid.');

      // Cleanup
      $message = str_replace(Array("\'", '\"'), Array("'", '"'), $message);

      // Ensure line endings are proper
      $message = str_replace(Array("\r\n", "\r", "\n"), Array("\n", '', '<br/>'), $message);

      // Generating boundaries
      $boundary1 = 'xmail' . Xmail::VERSION . '-' . md5(uniqid(rand()));
      $boundary2 = 'xmail' . Xmail::VERSION . '-' . md5(uniqid(rand()));

      // Content parts start
      $content  = $this->line;
      $content .= 'This is a multi-part message in MIME format.' . $this->line . $this->line;
      $content .= '--' . $boundary1 . $this->line;
      $content .= 'Content-Type: multipart/alternative;' . $this->line;
      $content .= ' boundary="' . $boundary2 . '"' . $this->line . $this->line;

      // Text part headers
      $content .= '--' . $boundary2 . $this->line;
      $content .= 'Content-Type: text/plain;' . $this->line;
      $content .= ' charset="UTF-8"' . $this->line;
      $content .= 'Content-Transfer-Encoding: base64' . $this->line . $this->line;

      // Text part content
      $content .= chunk_split(base64_encode(trim($this->makePlain($message)))) . $this->line;

      // HTML part headers
      $content .= '--' . $boundary2 . $this->line;
      $content .= 'Content-Type: text/html;' . $this->line;
      $content .= ' charset="UTF-8"' . $this->line;
      $content .= 'Content-Transfer-Encoding: base64' . $this->line . $this->line;

      // HTML part content
      $html  = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">' . $this->line;
      $html .= '<html>' . $this->line;
      $html .= '<body>' . $this->line;
      $html .= $message . $this->line;
      $html .= '</body>' . $this->line;
      $html .= '</html>' . $this->line;
      $content .= chunk_split(base64_encode($html)) . $this->line;

      // Content parts end
      $content .= '--' . $boundary2 . '--' . $this->line . $this->line;

      // Add attachments if any
      if(count($attachments) > 0) {

        // Loop attachments list
        foreach($attachments AS $file_url) {

          // Check path is valid file
          if(is_string($file_url) && is_file($file_url)) {
            // Get file info
            $file_name = pathinfo($file_url, PATHINFO_BASENAME);
            $file_type = $this->getExtensionMime(pathinfo($file_url, PATHINFO_EXTENSION));

            // Attachment headers
            $content .= '--' . $boundary1 . $this->line;
            $content .= 'Content-Type: ' . $file_type . ';' . $this->line;
            $content .= ' name="' . $file_name . '"' . $this->line;
            $content .= 'Content-Transfer-Encoding: base64' . $this->line;
            $content .= 'Content-Disposition: attachment;' . $this->line;
            $content .= ' filename="' . $file_name . '"' . $this->line . $this->line;

            // Attachment read
            $payload = '';
            $fp = fopen($file_url, 'r');
            while(True) {
              $data = fread($fp, 8192);
              if(strlen($data) == 0)
               break;
              $payload .= $data;
            }

            // Attachment content and encoding
            $content .= chunk_split(base64_encode($payload)) . $this->line . $this->line;
          }
        }
      }
      $content .= '--' . $boundary1 . '--' . $this->line . $this->line;

      // Add MIME headers
      $headers = trim($headers) . $this->line;
      $headers .= 'MIME-Version: 1.0' . $this->line;
      $headers .= 'Content-Type: multipart/mixed;' . $this->line;
      $headers .= ' boundary="' . $boundary1 . '"' . $this->line;

      $this->email = Array($headers, $content);
    }


    /**
     * Xmail::smtp()
     *
     * SMTP protocol method.
     *
     * @syntax Xmail::smtp($to, $subject, $message, $headers);
     * @param  string  $to       Receipient email address.
     * @param  string  $subject  Subject of the email.
     * @param  string  $message  Email message in HTML format.
     * @param  string  $headers  Email headers except MIME-Version, Content-Type, Subject, To.
     * @return boolean Result of email delivery.
     */
    private function smtp($to, $subject, $message, $headers) {
      $ret = False;

      // Choose server depending on selected mode
      if($this->mode == 'mx') {
        list(, $domain) = explode('@', $to);
        $dnsmx = $this->getRandMXIP($domain);
        $server = $dnsmx['A'][0];
      }
      else // SMTP
       $server = $this->smtp_host;

      // Open socket
      $socket = @fsockopen($server, $this->port, $errno, $errstr, $this->time);
      if(empty($socket)) {
        $this->log['SOCKET'] = 'Unable to conect to server.';
        goto end;
      }
      if($this->smtpParse($socket, 220, 'SOCKET') != 220) goto end;

      // Send Enhanced HELO
      @fputs($socket, 'EHLO ' . $this->host . $this->line);
      if($this->smtpParse($socket, 250, 'HELO') != 250) goto end;

      // Authenticate if in SMTP mode
      if($this->mode == 'smtp' && !empty($this->smtp_user) && !empty($this->smtp_pass) ) {
        // Send plain AUTH mechanism
        @fputs($socket, 'AUTH LOGIN' . $this->line);
        if($this->smtpParse($socket, 334, 'AUTH LOGIN') != 334) goto end;

        // Send username
        @fputs($socket, base64_encode($this->smtp_user) . $this->line);
        if($this->smtpParse($socket, 334, 'USERNAME') != 334) goto end;

        // Send password
        @fputs($socket, base64_encode($this->smtp_pass) . $this->line);
        if($this->smtpParse($socket, 235, 'PASSWORD') != 235) goto end;
      }

      // Send sender
      @fputs($socket, 'MAIL FROM: <' . $this->from . '>' . $this->line);
      if($this->smtpParse($socket, 250, 'MAIL FROM') != 250) goto end;

      // Send receipient
      @fputs($socket, 'RCPT TO: <' . $to . '>' . $this->line);
      if($this->smtpParse($socket, 250, 'RCPT TO') != 250) goto end;

      // If not testing send message content
      if($this->test != True) {

        // Send data start command
        @fputs($socket, 'DATA' . $this->line);
        if($this->smtpParse($socket, 354, 'DATA') != 354) goto end;

        // Send headers and content
        @fputs($socket, 'Subject: ' . $subject . $this->line);
        @fputs($socket, 'To: ' . $to . $this->line);
        @fputs($socket, $headers . $this->line);
        @fputs($socket, $message . $this->line);

        // Send a dot to show we're finished
        @fputs($socket, '.' . $this->line); // this line sends a dot to mark the end of message
        if($this->smtpParse($socket, 250, '.') != 250) goto end;
      }

      // Send QUIT
      @fputs($socket,'QUIT' . $this->line);
      $this->smtpParse($socket, 221, 'QUIT');
      $ret = True;

      // Close connection and return
      end:
      if($socket)
       @fclose($socket);
      return $ret;
    }


    /**
     * Xmail::smtpParse()
     *
     * Parse SMTP remote server responses.
     *
     * @syntax Xmail::smtpParse($socket, $expected, $key);
     * @param  string  $socket    SMTP connection socket.
     * @param  integer $expected  Expected response code.
     * @param  string  $key       Logging key.
     * @return integer Response code.
     */
    private function smtpParse($socket, $expected, $key) {
      $i = 0;
      $response = '';
      $this->log[$key] = '';
      while(substr($response, 3, 1) != ' ') {
        if(!($response = fgets($socket, 256))) $this->log['ERROR RESPONSE'] = 'Could not get mail server response codes.';
        else $this->log[$key] .= $response;

        // To avoid a deadlock break the loop after 20 as this should not happen ever
        $i++;
        if($i == 20) return False;
      }

      // Log  error if expected code not received
      if(substr($response, 0, 3) != $expected) $this->log['ERROR CODES'] = 'Ran into problems sending Mail. Received: ' . substr($response, 0, 3) . '.. but expected: ' . $expected;

      // Access denied... Quit
      if(substr($response, 0, 3) == 451) $this->log['ERROR QUIT'] = 'Server declined access. Quitting.';

      return substr($response, 0, 3);
    }


    /**
     * Xmail::getRandMXIP()
     *
     * Prepare getRandMX() returned IPs as a simple list.
     *
     * @syntax Xmail::getRandMXIP($domain);
     * @param  string  $domain  Domain to get MX records IP list for.
     * @return array   Array of MX server IPs.
     */
    private function getRandMXIP($domain) {
      $array = $this->getRandMX($domain);

      $return = Array('A' => Array(), 'AAAA' => Array());
      foreach($array['mx'] AS $mx) {
        foreach($mx AS $re) {
          foreach($array['ip'][$re]['A'] AS $ip) {
            $return['A'][] = $ip['ip'];
          }
          foreach($array['ip'][$re]['AAAA'] AS $ip) {
            $return['AAAA'][] = $ip['ipv6'];
          }
        }
      }
      return $return;
    }


    /**
     * Xmail::getRandMX()
     *
     * Get DNS MX records and their IPs and randomize based on priority
     *
     * @syntax Xmail::getRandMX($domain);
     * @param  string  $domain  Domain to get MX records for.
     * @return array   Array of MX servers and IPs.
     */
    private function getRandMX($domain) {
      getmxrr($domain, $hosts, $weight);

      if(!$hosts) {
        $hosts = Array($domain);
        $getmxrr = Array(0);
      }

      $ips = Array();
      $mxs = Array();
      foreach($hosts AS $key => $val) {
        if(empty($mxs[$getmxrr[$key]]))
         $mxs[$getmxrr[$key]] = Array();

        $mxs[$getmxrr[$key]][] = $val;
        $ips[$val]['A'] = dns_get_record($val, DNS_A);
        $ips[$val]['AAAA'] = dns_get_record($val, DNS_AAAA);
      }
      ksort($mxs);
      foreach($mxs AS $key => $val) {
        shuffle($mxs[$key]);
      }
      foreach($ips AS $key => $val) {
        shuffle($ips[$key]['A']);
        shuffle($ips[$key]['AAAA']);
      }
      return Array('mx' => $mxs, 'ip' => $ips);
    }


    /**
     * Xmail::makePlain()
     *
     * Transform HTML in plain text.
     *
     * @syntax Xmail::makePlain($source);
     * @param  string  $source  HTML content.
     * @return string  Plain text content.
     */
    private function makePlain($source) {
      // change new lines to \n only
      $source = str_replace("\r", "", $source);
      
      $message = $source;
      
      // pre strip replace
      $search  = Array(
                       "/\r/", "/\n/", "/\t/",                                // take out newlines and tabs (\n replaced with space)
                       "/[ ]{2,}/",                                           // runs of space
                       "/<br\s*\/?>/i",                                       // HTML line breaks
                       "/<hr[^>]*>/i",                                        // <hr>
                       "/(<table[^>]*>|<\/table>)/i",                         // <table> and </table>
                       "/(<tr[^>]*>|<\/tr>)/i",                               // <tr> and </tr>
                       "/<td[^>]*>(.*?)<\/td>/i",                             // <td> and </td>
                       "/&(nbsp|#160);/i",                                    // Non-breaking space
                       "/&(quot|rdquo|ldquo|#8220|#8221|#147|#148);/i",       // Double quotes
                       "/&(apos|rsquo|lsquo|#8216|#8217);/i",                 // Single quotes
                       "/&gt;/i",                                             // Greater-than
                       "/&lt;/i",                                             // Less-than
                       "/&(amp|#38);/i",                                      // Ampersand
                       "/&(copy|#169);/i",                                    // Copyright
                       "/&(trade|#8482|#153);/i",                             // Trademark
                       "/&(reg|#174);/i",                                     // Registered
                       "/&(mdash|#151|#8212);/i",                             // mdash
                       "/&(ndash|minus|#8211|#8722);/i",                      // ndash
                       "/&(bull|#149|#8226);/i",                              // Bullet
                       "/&(pound|#163);/i",                                   // Pound sign
                       "/£/i",                                                // Pound sign
                       "/&(euro|#8364);/i",                                   // Euro sign
                       "/&#?[a-z0-9]+;/i",                                    // Unknown/unhandled entities
                       );
      
      $replace = Array(
                       "", " ", " ",                                          // take out newlines
                       " ",                                                   // runs of space
                       "\n",                                                  // HTML line breaks
                       "\n----------------------------------\n",              // <hr>
                       "\n\n",                                                // <table> and </table>
                       "\n",                                                  // <tr> and </tr>
                       "\t\t\\1\n",                                           // <td> and </td>
                       " ",                                                   // Non-breaking space
                       '"',                                                   // Double quotes
                       "'",                                                   // Single quotes
                       ">",                                                   // Greater-than
                       "<",                                                   // Less-than
                       "&",                                                   // Ampersand
                       "(c)",                                                 // Copyright
                       "(tm)",                                                // Trademark
                       "(R)",                                                 // Registered
                       "--",                                                  // mdash
                       "-",                                                   // ndash
                       "*",                                                   // Bullet
                       "GBP",                                                 // Pound sign
                       "GBP",                                                 // Pound sign
                       "EUR",                                                 // Euro sign.
                       "",                                                    // Unknown/unhandled entities
                                   );
      $message = preg_replace($search, $replace, $message);
      
      // <th> and </th>
      $message = preg_replace_callback("/<th[^>]*>(.*?)<\/th>/i", function($matches) {
        return "\t\t" . strtoupper($matches[1]) . "\t\t";
      }, $message);
      
      // uppercase headers
      $message = preg_replace_callback("/(<h([1-6])>(.*?)<\/h\1>)/i", function($matches) {
        return "\n\n" . strtoupper($matches[2]) . "\n\n";
      }, $message);
      
      $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
      
      
      // strip tags
      $message = strip_tags($message);
      
      
      // post strip replace
      $search  = Array(
                       "/\n\s+\n/", "/[\n]{3,}/", "/^[ ]+/mi", "/[ ]{2,}/i",  // fix multiple spaces and newlines
                       "/\t/",                                                // fix tabs
                       "/[ ]{5}/",                                            // fix 5 spaces
                       );
      
      $replace = Array(
                       "\n\n", "\n\n", "", "",                                // fix multiple spaces and newlines
                       "  ",                                                  // fix tabs
                       "    ",                                                // fix 5 spaces
                       );
      $message = preg_replace($search, $replace, $message);
      
      
      // find and add links at the end
      preg_match_all("/(<a.*?href=)(\"|'| )(.*?)(\"|'| )(.*?\>)(.*?)(<\/\s*a\s*>)/i", $source, $array);
      if(count($array[3]) > 0) $message .= "\n\nLinks:\n";
      $array[3] = array_unique($array[3]);
      foreach($array[3] AS $link) {
        $message .= "<" . $link . ">\n";
      }
      
      
      // wrap and trim
      $message = wordwrap($message);
      $message = trim(rtrim($message));
      
      
      // change new lines back to \r\n only
      $message = str_replace("\n", "\r\n", $message);
      
      
      // return nice plain text
      return $message;
    }


    /**
     * Xmail::getExtensionMime()
     *
     * Get MIME type for extension.
     *
     * @syntax Xmail::getExtensionMime($ext);
     * @param  string  $ext  File extension.
     * @return string  Associated MIME type.
     */
    private function getExtensionMime($ext) {
      // Default return octet-stream
      $ret = 'application/octet-stream';

      // Get MIME types
      $types = $this->mimeTypes();

      // Return MIME type for extension
      if(isset($types[$ext]))
       $ret = $types[$ext];

      return $ret;
    }


    /**
     * Xmail::mimeTypes()
     *
     * Array of popular MIME types.
     * PHP might not have mime support thus this.
     *
     * @syntax Xmail::mimeTypes();
     * @return array  MIME types.
     */
    private function mimeTypes() {
      return array(
        'ez'      => 'application/andrew-inset',
        'hqx'     => 'application/mac-binhex40',
        'cpt'     => 'application/mac-compactpro',
        'doc'     => 'application/msword',
        'bin'     => 'application/octet-stream',
        'dms'     => 'application/octet-stream',
        'lha'     => 'application/octet-stream',
        'lzh'     => 'application/octet-stream',
        'exe'     => 'application/octet-stream',
        'class'   => 'application/octet-stream',
        'so'      => 'application/octet-stream',
        'dll'     => 'application/octet-stream',
        'oda'     => 'application/oda',
        'pdf'     => 'application/pdf',
        'ai'      => 'application/postscript',
        'eps'     => 'application/postscript',
        'ps'      => 'application/postscript',
        'smi'     => 'application/smil',
        'smil'    => 'application/smil',
        'wbxml'   => 'application/vnd.wap.wbxml',
        'wmlc'    => 'application/vnd.wap.wmlc',
        'wmlsc'   => 'application/vnd.wap.wmlscriptc',
        'bcpio'   => 'application/x-bcpio',
        'vcd'     => 'application/x-cdlink',
        'pgn'     => 'application/x-chess-pgn',
        'cpio'    => 'application/x-cpio',
        'csh'     => 'application/x-csh',
        'dcr'     => 'application/x-director',
        'dir'     => 'application/x-director',
        'dxr'     => 'application/x-director',
        'dvi'     => 'application/x-dvi',
        'spl'     => 'application/x-futuresplash',
        'gtar'    => 'application/x-gtar',
        'hdf'     => 'application/x-hdf',
        'js'      => 'application/x-javascript',
        'skp'     => 'application/x-koan',
        'skd'     => 'application/x-koan',
        'skt'     => 'application/x-koan',
        'skm'     => 'application/x-koan',
        'latex'   => 'application/x-latex',
        'nc'      => 'application/x-netcdf',
        'cdf'     => 'application/x-netcdf',
        'sh'      => 'application/x-sh',
        'shar'    => 'application/x-shar',
        'swf'     => 'application/x-shockwave-flash',
        'sit'     => 'application/x-stuffit',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc'  => 'application/x-sv4crc',
        'tar'     => 'application/x-tar',
        'tcl'     => 'application/x-tcl',
        'tex'     => 'application/x-tex',
        'texinfo' => 'application/x-texinfo',
        'texi'    => 'application/x-texinfo',
        't'       => 'application/x-troff',
        'tr'      => 'application/x-troff',
        'roff'    => 'application/x-troff',
        'man'     => 'application/x-troff-man',
        'me'      => 'application/x-troff-me',
        'ms'      => 'application/x-troff-ms',
        'ustar'   => 'application/x-ustar',
        'src'     => 'application/x-wais-source',
        'xhtml'   => 'application/xhtml+xml',
        'xht'     => 'application/xhtml+xml',
        'zip'     => 'application/zip',
        'au'      => 'audio/basic',
        'snd'     => 'audio/basic',
        'mid'     => 'audio/midi',
        'midi'    => 'audio/midi',
        'kar'     => 'audio/midi',
        'mpga'    => 'audio/mpeg',
        'mp2'     => 'audio/mpeg',
        'mp3'     => 'audio/mpeg',
        'aif'     => 'audio/x-aiff',
        'aiff'    => 'audio/x-aiff',
        'aifc'    => 'audio/x-aiff',
        'm3u'     => 'audio/x-mpegurl',
        'ram'     => 'audio/x-pn-realaudio',
        'rm'      => 'audio/x-pn-realaudio',
        'rpm'     => 'audio/x-pn-realaudio-plugin',
        'ra'      => 'audio/x-realaudio',
        'wav'     => 'audio/x-wav',
        'pdb'     => 'chemical/x-pdb',
        'xyz'     => 'chemical/x-xyz',
        'bmp'     => 'image/bmp',
        'gif'     => 'image/gif',
        'ief'     => 'image/ief',
        'jpeg'    => 'image/jpeg',
        'jpg'     => 'image/jpeg',
        'jpe'     => 'image/jpeg',
        'png'     => 'image/png',
        'tiff'    => 'image/tiff',
        'tif'     => 'image/tif',
        'djvu'    => 'image/vnd.djvu',
        'djv'     => 'image/vnd.djvu',
        'wbmp'    => 'image/vnd.wap.wbmp',
        'ras'     => 'image/x-cmu-raster',
        'pnm'     => 'image/x-portable-anymap',
        'pbm'     => 'image/x-portable-bitmap',
        'pgm'     => 'image/x-portable-graymap',
        'ppm'     => 'image/x-portable-pixmap',
        'rgb'     => 'image/x-rgb',
        'xbm'     => 'image/x-xbitmap',
        'xpm'     => 'image/x-xpixmap',
        'xwd'     => 'image/x-windowdump',
        'igs'     => 'model/iges',
        'iges'    => 'model/iges',
        'msh'     => 'model/mesh',
        'mesh'    => 'model/mesh',
        'silo'    => 'model/mesh',
        'wrl'     => 'model/vrml',
        'vrml'    => 'model/vrml',
        'css'     => 'text/css',
        'html'    => 'text/html',
        'htm'     => 'text/html',
        'asc'     => 'text/plain',
        'txt'     => 'text/plain',
        'rtx'     => 'text/richtext',
        'rtf'     => 'text/rtf',
        'sgml'    => 'text/sgml',
        'sgm'     => 'text/sgml',
        'tsv'     => 'text/tab-seperated-values',
        'wml'     => 'text/vnd.wap.wml',
        'wmls'    => 'text/vnd.wap.wmlscript',
        'etx'     => 'text/x-setext',
        'xml'     => 'text/xml',
        'xsl'     => 'text/xml',
        'mpeg'    => 'video/mpeg',
        'mpg'     => 'video/mpeg',
        'mpe'     => 'video/mpeg',
        'qt'      => 'video/quicktime',
        'mov'     => 'video/quicktime',
        'mxu'     => 'video/vnd.mpegurl',
        'avi'     => 'video/x-msvideo',
        'movie'   => 'video/x-sgi-movie',
        'ice'     => 'x-conference-xcooltalk'
      );
    }
  }


  /**
   * getmxrr()
   *
   * PHP does not have getmxr function on windows so heres built one.
   *
   * @ignore
   *
   * @syntax getmxrr($hostname, &$mxhosts, &$mxweight = False);
   * @param  string  $hostname  Domain to get MX records for.
   * @param  string  $mxhosts   Result records by refference.
   * @param  string  $mxweight  Result weight by refference.
   * @return boolean
   */
  if(!function_exists('getmxrr')) {
    function getmxrr($hostname, &$mxhosts, &$mxweight = False) {
      if(strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') return;
      if(!is_array ($mxhosts) ) $mxhosts = array();
      if(empty($hostname)) return;
      $exec = 'nslookup -type=MX '.escapeshellarg($hostname);
      @exec($exec, $output);
      if (empty($output)) return;
      $i = -1;
      foreach($output as $line) {
        $i++;
        if(preg_match('/^' . $hostname . "\tMX preference = ([0-9]+), mail exchanger = (.+)$/i", $line, $parts)) {
          $mxweight[$i] = trim($parts[1]);
          $mxhosts[$i] = trim($parts[2]);
        }
        if(preg_match('/responsible mail addr = (.+)$/i', $line, $parts)) {
          $mxweight[$i] = $i;
          $mxhosts[$i] = trim($parts[1]);
        }
      }
      return ($i != -1);
    }
  }


  /**
   * xmail()
   *
   * Example handler function for Xmail class.
   *
   * @ignore
   */
  function xmail($to, $subject, $message, $headers = '', $attachments = '') {
    // Sender
    $sender_name    = 'John Doe';
    $sender_address = 'john.doe';
    $sender_domain  = 'example.com';

    // Instanciate
    $xmail = new Xmail(True);

    // Test mode
    $xmail->setTest(False); // (Boolean: default False) Test without sending the data

    // Delivery mode
    $xmail->setMode('mail'); // (mail, smtp, mx: default mail) Choose mode of delivery.

    // MX setup if in 'mx' mode
    $xmail->setFrom($sender_address . '@' . $sender_domain);
    $xmail->setHost($sender_domain);
    $xmail->setTime(5);

    // SMTP SETUP if in 'smtp' mode
    $xmail->setSmtpHost($sender_domain);
    $xmail->setSmtpPort(25);
    $xmail->setSmtpUser('username');
    $xmail->setSmtpPass('password');

    // Generic From header
    if(empty($headers))
     $headers = 'From: "' . $sender_name . '" <' . $sender_address . '@' . $sender_domain . ">\r\n";

    // Compose
    $xmail->compose($message, $headers, $attachments);

    // Send
    $xmail->mail($to, $subject, $message, $headers, $attachments);

    return $xmail;
  }


  /**
   * xmail_test()
   *
   * Test handler function for example handler function.
   *
   * @ignore
   */
  function xmail_test() {
    header('Content-type: text/plain');
    print_r(
            xmail(
                  // To
                  'jane.doe@example.com',

                  // Subject
                  'Xmail delivery test',
                  
                  // Message
                  'Dear reader,<br/><br/>' .
                  'This is a test message sent by Xmail ' . Xmail::VERSION . ' ' . Xmail::NAME . '.<br/>' .
                  'A developer is running email delivery tests.<br/>' .
                  'Please excuse the disturbance and ignore this and future such emails.<br/><br/>' .
                  'Thank you.',

                  // Headers
                  '',

                  // Attachments
                  Array()
                  )
            );
  }
