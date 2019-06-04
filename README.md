# vBulletin-wmail
WMail adds POP3/SMTP access to vBulletin. The original author released this under the GPL. Its for vBulletin until 3.8. I only upload this for archive reasons.


### What is this?
WMail is a "webmail client" - a front end, like a mail tool installed locally on your PC, for using POP3/SMTP mailboxes.

In short:
WMail adds POP3/SMTP access to vBulletin.

Key features:

 * The obvious: Getting mailbox content, reading mails from every POP3 mailbox and sending mails through any SMTP server (configurable in the user options)
 * written from scratch, spezificially to be used with vBulletin 3.5.x - not a port of something
 * fully phrased and templated down to the last bit
 * Full multipart support for downloading (and sending) attachments
 * When reading mail the mail text is parsed for BB-Codes, so you get the graphical smillies from the forum, text formatting and URLs automatically become clickable links
 * When replying to, or forwarding, mail the text of the original mail is quoted, propper quotemarks are added and a quote-header is added (customizable via template)
 * various options for admin and users to customize the webmail client
 * Does NOT require any special modules to be compiled into your PHP installation (like the IMAP libraries)
 * (for techies: this uses basic socket connections instead of special PHP function calls)
 * Admin can override certain aspects of the user config (forcing to use a given mailserver, force using forum email address and such)
 * Write your mails using vB's WYSIWYG editor
 * buildin (yet simple) addressbook for your E-Mail contacts
 * Read/Unread markings - unread mails are highlighted, options for "mark selected read", "mark all read" and "mark selected unread"
 * custom hooks to make it easy to develop addons for it
 * automatically adds links to the webmailer in "Quick Links" menu, navbar and UserCP. Usualy no template edits needed when using default ones (can be disabled for each link in admin options)
