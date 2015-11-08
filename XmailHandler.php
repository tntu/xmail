<?php

  /**
   * XmailHandler
   *
   * Configuration class for Xmail class handler function xmail().
   *
   * @filesource
   *
   * @category Email
   * @package \email
   * @subpackage \email\xmail
   *
   * @version 20151108
   * @since 20151108
   *
   * @author "Vlad Marian" <transilvlad@gmail.com>
   * @copyright Vlad Marian 2015
   * @license http://www.gnu.org/licenses/agpl.txt GPL v3
   * @link http://transilvlad.com Author Website
   *
   * @see xmail()
   * @see Xmail
   */
  final class XmailHandler {
    /**
     * XmailHandler::$name
     *
     * Default sender name
     */
    public static $name = "John Doe";

    /**
     * XmailHandler::$address
     *
     * Default sender address
     */
    public static $address = "john.doe";

    /**
     * XmailHandler::$domain
     *
     * Default sender domain
     */
    public static $domain = "example.com";

    /**
     * XmailHandler::$test
     *
     * Run in test mode
     */
    public static $test = False;

    /**
     * XmailHandler::$mode
     *
     * Email delivery method
     * Options: mail, smtp, mx
     * Default mx
     */
    public static $mode = "mx";

    /**
     * XmailHandler::$smtp_port
     *
     * SMTP port if other than default 25
     */
    public static $smtp_port = 25;

    /**
     * XmailHandler::$smtp_user
     *
     * SMTP login username
     */
    public static $smtp_user = "";

    /**
     * XmailHandler::$smtp_pass
     *
     * SMTP login password
     */
    public static $smtp_pass = "";

    /**
     * XmailHandler::mail()
     *
     * Easy to use handler for Xmail class.
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param string $headers
     * @param array $attachments
     * @return Xmail
     */
    public static function mail($to, $subject, $message, $headers = '', $attachments = Array()) {
      $xmail = new Xmail();

      // Test mode
      $xmail->setTest(self::$test);

      // Delivery mode
      $xmail->setMode(self::$mode);

      // MX setup if in 'mx' mode
      $xmail->setFrom(self::$address . '@' . self::$domain);
      $xmail->setHost(self::$domain);
      $xmail->setTime(5);

      // SMTP SETUP if in 'smtp' mode
      $xmail->setSmtpHost(self::$domain);
      $xmail->setSmtpPort(self::$smtp_port);
      $xmail->setSmtpUser(self::$smtp_user);
      $xmail->setSmtpPass(self::$smtp_pass);

      // Generic From header
      if(empty($headers)) {
        $headers = 'From: "' . self::$name . '" <' . self::$address . '@' . self::$domain . ">\r\n";
      }

      // Compose
      $xmail->compose($message, $headers, $attachments);

      // Send
      $xmail->mail($to, $subject, $message, $headers, $attachments);

      return $xmail;
    }
  }
