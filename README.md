Easy Imap
==========

EasyImap is a PHP class to access IMAP mail server via OpenSSL

Features:
- Access IMAP mail server using openssl.

- Login to your Exchange account.

- Search through emails.

- Fetch and parse emails into html and text versions.


Also this Class can execute imap commands such as : fetch , select , login, logout .. etc


Examples :
==========

- Openssl connection:
---------------------

  IMAP command  : openssl s_client -connect server:port -crlf

  Easy Imap : $imap = new EasyImap('server', 'port', true);

- Login :
---------
  IMAP command : TAG1 LOGIN email password

  Easy Imap : $imap->send('LOGIN', array('email', 'password'));
  
- Examine :
-----------
  IMAP command : TAG2 EXAMINE "INBOX"

  Easy Imap : $imap->select('INBOX');
  
- Search :
----------
  IMAP command : TAG3 uid SEARCH SINCE date
  
  Easy Imap : $imap->search(array('SINCE', $date));


- Fetch :
---------
  IMAP command : TAG5 uid FETCH 12990,12992,12993 BODYSTRUCTURE

  Easy Imap : $imap->fetch(array('BODYSTRUCTURE'), array(12990,12992,12993) );
  
  
  IMAP command : TAG6 uid FETCH 12990 (body.peek[1] body.peek[1] body[header.fields (Message-ID from subject)] INTERNALDATE)
  
  Easy Imap : $this->fetch(array("body.peek[1]", "body.peek[1]","body[header.fields (Message-ID from subject)]", "INTERNALDATE"), array(12990));


for more examples read example.php


Author
==========
Enas Hasan


