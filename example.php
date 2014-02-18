
<?php
include('EasyImap.php');

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */

$imap = new EasyImap('imap.gmail.com', '993', true);
$imap->send('LOGIN', array('email@gmail.com', 'password'));
$imap->select('INBOX');
$connection_date = date('j-M-Y', (time() - (30 * 24 * 60 * 60 )));
$ids = $imap->search(array('SINCE', $connection_date));

$data = $imap->getMessage($ids);
$ids = array_keys($data);
$emails_count = count($ids);
$gmail_emails = array();
for($y = 0; ($y < $emails_count); $y=$y+3 ){
  $email_ids = array_slice($ids, $y, 3);
  $gmail_emails = $imap->getMessage($email_ids, false);
}

print_r($gmail_emails);

$imap = new EasyImap('imap-ssl.mail.yahoo.com', '993', true);
$imap->send('LOGIN', array('email@yahoo.com', 'password'));
$imap->select('INBOX');
$connection_date = date('j-M-Y', (time() - (30 * 24 * 60 * 60 )));
$ids = $imap->search(array('SINCE', $connection_date));

$data = $imap->getMessage($ids);

$emails_count = count($ids);
$yahoo_emails = array();
for($y = 0; ($y < $emails_count); $y=$y+3 ){
  $email_ids = array_slice($ids, $y, 3);
  $yahoo_emails = $imap->getMessage($email_ids, false);
}

print_r($yahoo_emails);
