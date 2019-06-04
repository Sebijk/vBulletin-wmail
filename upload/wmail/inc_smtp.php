<?php

/* =============================================================== *\
|     WMail - vBulletin Web-Mail Client for POP3/SMTP mailboxes     |
+-------------------------------------------------------------------+
|     by ---==> HELLCAT <==--- (Michael Hengstmann)                 |
|     hellcat@hellcat.net                                           |
|                                                                   |
|     This software is puplished under the                          |
|     GNU General Puplic License - see GPL.TXT for details          |
\* =============================================================== */

// Special note on INC_SMTP.PHP:
// This file is based on work of
//   Nicolas Chalanset <nicocha@free.fr>
//   Olivier Cahagne <cahagn_o@epita.fr>
//   Unk <rgroesb_garbage@triple-it_garbage.nl>  
//
// wich was also published under the GPL

$v_inc["smtp"] = "5";

class smtp
{
    var $smtp_server;
    var $port;
    var $from;
    var $to;
    var $cc;
    var $bcc;
    var $subject;
    var $data;
    var $sessionlog = '';
        
    // This function is the constructor don't forget this one
    function smtp()
    {
        $this->smtp_server = '';
        $this->port = '';
        $this->from = '';
        $this->to = Array();
        $this->cc = Array();
        $this->bcc = Array();
        $this->subject = '';
        $this->data = '';
    }

    function smtp_open() 
    {
	    $this->sessionlog .= "\\Connection to " . $this->smtp_server . ":" . $this->port . "....";
        $smtp = fsockopen($this->smtp_server, $this->port, $errno, $errstr); 
        if (!$smtp) {
		    $this->sessionlog .= "failed: " . $errstr;
            return false;
			}
        
		$line = "";
		do
		{
		  $s = fgets($smtp, 1024);
		  $line .= $s;
		} while ( substr( $s, 3, 1 ) == "-" );

        if (substr($line, 0, 1) != '2') {
		    $this->sessionlog .= "failed unexpexted! (Are there expected failures???)";
            return false;
			}
        
		$this->sessionlog .= "\\Rcvd: $line";
        return $smtp;
    } 
    
    function smtp_helo($smtp) 
    {
        /* 'localhost' always works [Unk] */ 
        fputs($smtp, "helo localhost\r\n"); 
        $this->sessionlog .= "\\Sent: helo localhost";
		
		$line = "";
		do
		{
		  $s = fgets($smtp, 1024);
		  $line .= $s;
		} while ( substr( $s, 3, 1 ) == "-" );
		
        $this->sessionlog .= "\\Rcvd: $line";

        if (substr($line, 0, 1) != '2')
            return false;
        
        return (true);
    } 
  
    function smtp_ehlo($smtp) 
    {
        fputs($smtp, "ehlo localhost\r\n"); 
        $this->sessionlog .= "\\Sent: ehlo localhost";
		
		$line = "";
		do
		{
		  $s = fgets($smtp, 1024);
		  $line .= $s;
		} while ( substr( $s, 3, 1 ) == "-" );
		
        $this->sessionlog .= "\\Rcvd: $line";

        if (substr($line, 0, 1) != '2')
            return false;

        return (true);
    }
    
    function smtp_auth($smtp, $auth, $user = "", $pass = "")
    {
      switch ( $auth ) {
          case 'LOGIN':
              fputs($smtp, "auth login\r\n"); 
              $this->sessionlog .= "\\Sent: auth login";
              $line = "";
		      do
		      {
	       	    $s = fgets($smtp, 1024);
		        $line .= $s;
		      } while ( substr( $s, 3, 1 ) == "-" );
              $this->sessionlog .= "\\Rcvd: $line";
              if (substr($line, 0, 1) != '3')
                  return false;
                  
              fputs($smtp, base64_encode($user) . "\r\n"); 
              $this->sessionlog .= "\\Sent: encoded login";
              $line = "";
		      do
		      {
		        $s = fgets($smtp, 1024);
		        $line .= $s;
		      } while ( substr( $s, 3, 1 ) == "-" );
              $this->sessionlog .= "\\Rcvd: $line";
              if (substr($line, 0, 1) != '3')
                  return false;
                  
              fputs($smtp, base64_encode($pass) . "\r\n"); 
              $this->sessionlog .= "\\Sent: encoded password";
              $line = "";
		      do
		      {
		        $s = fgets($smtp, 1024);
		        $line .= $s;
		      } while ( substr( $s, 3, 1 ) == "-" );
              $this->sessionlog .= "\\Rcvd: $line";
              if (substr($line, 0, 1) != '2')
                  return false;
              return (true);
              break;
          case 'PLAIN':
              fputs($smtp, "auth plain " . base64_encode($user . chr(0) . $user . chr(0) . $pass) . "\r\n");
              $this->sessionlog .= "\\Sent: encoded auth";
              $line = "";
		      do
		      {
		        $s = fgets($smtp, 1024);
		        $line .= $s;
		      } while ( substr( $s, 3, 1 ) == "-" );
              $this->sessionlog .= "\\Rcvd: $line";
              if (substr($line, 0, 1) != '2')
	          return false;
              return (true);
              break;
          case '':
              return (true);
      }
      return (true);
    }

    function smtp_mail_from($smtp) 
    {
        fputs($smtp, "MAIL FROM:$this->from\r\n"); 
        $this->sessionlog .= "\\Sent: MAIL FROM:$this->from";
        
		$line = "";
		do
		{
		  $s = fgets($smtp, 1024);
		  $line .= $s;
		} while ( substr( $s, 3, 1 ) == "-" );
		
        $this->sessionlog .= "\\Rcvd: $line";

        if (substr($line, 0, 1) <> '2')
            return false;

        return (true);
    }

    function smtp_rcpt_to($smtp)
    {
        // Modified by nicocha to use to, cc and bcc field
        while ($tmp = array_shift($this->to))
        {
            if($tmp == '' || $tmp == '<>')
                continue;
            fputs($smtp, "RCPT TO:$tmp\r\n");
            $this->sessionlog .= "\\Sent: RCPT TO:$tmp";
            
			$line = "";
		      do
		      {
		        $s = fgets($smtp, 1024);
		        $line .= $s;
		      } while ( substr( $s, 3, 1 ) == "-" );
		
            $this->sessionlog .= "\\Rcvd: $line";

            if (substr($line, 0, 1) <> '2')
                return false;
        }
        while ($tmp = array_shift($this->cc))
        {
            if($tmp == '' || $tmp == '<>')
                continue;
            fputs($smtp, "RCPT TO:$tmp\r\n");
            $this->sessionlog .= "\\Sent: RCPT TO:$tmp";
            
			
			$line = "";
		    do
		    {
		      $s = fgets($smtp, 1024);
		      $line .= $s;
		    } while ( substr( $s, 3, 1 ) == "-" );
            $this->sessionlog .= "\\Rcvd: $line";

            if (substr($line, 0, 1) <> '2')
                return false;
        }

        while ($tmp = array_shift($this->bcc))
        {
            if($tmp == '' || $tmp == '<>')
                continue;
            fputs($smtp, "RCPT TO:$tmp\r\n");
            $this->sessionlog .= "\\Sent: RCPT TO:$tmp";
            
			$line = "";
		    do
		    {
		      $s = fgets($smtp, 1024);
		      $line .= $s;
		    } while ( substr( $s, 3, 1 ) == "-" );
		
            $this->sessionlog .= "\\Rcvd: $line";

            if (substr($line, 0, 1) <> '2')
                return false;
        }
        return (true);
    } 

    function smtp_data($smtp) 
    {
        fputs($smtp, "DATA\r\n"); 
        $this->sessionlog .= "\\Sent: DATA";
        
		$line = "";
		do
		{
		  $s = fgets($smtp, 1024);
		  $line .= $s;
		} while ( substr( $s, 3, 1 ) == "-" );
		
        $this->sessionlog .= "\\Rcvd: $line";

        if (substr($line, 0, 1) != '3')
            return false;
        
        fputs($smtp, "$this->data"); 
        fputs($smtp, "\r\n.\r\n"); 
        
		$line = "";
		do
		{
		  $s = fgets($smtp, 1024);
		  $line .= $s;
		} while ( substr( $s, 3, 1 ) == "-" );
		
        $this->sessionlog .= "\\Rcvd: $line";
        if (substr($line, 0, 1) !=  '2')
            return false;

        return (true);
    }
  
    function smtp_quit($smtp) 
    {
        fputs($smtp,  "QUIT\r\n"); 
        $this->sessionlog .= "\\Sent: QUIT";
        
		$line = "";
		do
		{
		  $s = fgets($smtp, 1024);
		  $line .= $s;
		} while ( substr( $s, 3, 1 ) == "-" );
		
        $this->sessionlog .= "\\Rcvd: $line";

        if (substr($line, 0, 1) !=  '2')
            return false;

        return (true);
    }
}
?>
