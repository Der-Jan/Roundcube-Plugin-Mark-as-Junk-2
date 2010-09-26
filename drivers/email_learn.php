<?php

/**
 * Email learn driver
 * @version 1.0
 * @author Philip Weir
 */
function learn_spam($uids)
{
	do_emaillearn($uids, true);
}

function learn_ham($uids)
{
	do_emaillearn($uids, false);
}

function do_emaillearn($uids, $spam)
{
	$rcmail = rcmail::get_instance();
	$identity_arr = $rcmail->user->get_identity();
	$from = $identity_arr['email'];

	if ($spam)
		$mailto = $rcmail->config->get('markasjunk2_email_spam');
	else
		$mailto = $rcmail->config->get('markasjunk2_email_ham');

	$mailto = str_replace('%u', $_SESSION['username'], $mailto);
	$mailto = str_replace('%l', $rcmail->user->get_username('local'), $mailto);
	$mailto = str_replace('%d', $rcmail->user->get_username('domain'), $mailto);
	$mailto = str_replace('%i', $from, $mailto);

	if (!$mailto)
		return;

	$message_charset = $rcmail->output->get_charset();
	// chose transfer encoding
	$charset_7bit = array('ASCII', 'ISO-2022-JP', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-15');
	$transfer_encoding = in_array(strtoupper($message_charset), $charset_7bit) ? '7bit' : '8bit';

	$temp_dir = realpath($rcmail->config->get('temp_dir'));

	$subject = $rcmail->config->get('markasjunk2_email_subject');
	$subject = str_replace('%u', $_SESSION['username'], $subject);
	$subject = str_replace('%t', ($spam) ? 'spam' : 'ham', $subject);
	$subject = str_replace('%l', $rcmail->user->get_username('local'), $subject);
	$subject = str_replace('%d', $rcmail->user->get_username('domain'), $subject);

	foreach (explode(",", $uids) as $uid) {
		$MESSAGE = new rcube_message($uid);
		$tmpPath = tempnam($temp_dir, 'rcmMarkASJunk2');

		// compose headers array
		$headers = array();
		$headers['Date'] = date('r');
		$headers['From'] = format_email_recipient($identity_arr['email'], $identity_arr['name']);
		$headers['To'] = $mailto;
		$headers['Subject'] = $subject;

		$MAIL_MIME = new Mail_mime($rcmail->config->header_delimiter());
		if ($rcmail->config->get('markasjunk2_email_attach', false)) {
			// send mail as attachment
			$MAIL_MIME->setTXTBody(($spam ? 'Spam' : 'Ham'). ' report from ' . $rcmail->config->get('product_name'), false, true);

			$message = $rcmail->imap->get_raw_body($uid);
			$subject = $MESSAGE->get_header('subject');

			if(isset($subject) && $subject !="")
				$disp_name = $subject . ".eml";
			else
				$disp_name = "message_rfc822.eml";

			if(file_put_contents($tmpPath, $message)){
				$MAIL_MIME->addAttachment($tmpPath, "message/rfc822", $disp_name, true,
					$ctype == 'message/rfc822' ? $transfer_encoding : 'base64',
					'attachment', $message_charset, '', '',
					$rcmail->config->get('mime_param_folding') ? 'quoted-printable' : NULL,
					$rcmail->config->get('mime_param_folding') == 2 ? 'quoted-printable' : NULL
				);
			}
		}
		else {
			if ($MESSAGE->has_html_part()) {
				$body = $MESSAGE->first_html_part();
				$MAIL_MIME->setHTMLBody($body);

				// add a plain text version of the e-mail as an alternative part.
				$h2t = new html2text($body, false, true, 0);
				$MAIL_MIME->setTXTBody($h2t->get_text());
			}
			else {
				$body = $MESSAGE->first_text_part();
				$MAIL_MIME->setTXTBody($body, false, true);
			}
		}

		// encoding settings for mail composing
		$MAIL_MIME->setParam('text_encoding', $transfer_encoding);
		$MAIL_MIME->setParam('html_encoding', 'quoted-printable');
		$MAIL_MIME->setParam('head_encoding', 'quoted-printable');
		$MAIL_MIME->setParam('head_charset', $message_charset);
		$MAIL_MIME->setParam('html_charset', $message_charset);
		$MAIL_MIME->setParam('text_charset', $message_charset);

		// pass headers to message object
		$MAIL_MIME->headers($headers);

		rcmail_deliver_message($MAIL_MIME, $from, $mailto, $smtp_error, $body_file);

		// clean up
		if (file_exists($tmpPath))
			unlink($tmpPath);

		if ($rcmail->config->get('markasjunk2_debug')) {
			if ($spam)
				write_log('markasjunk2', $uid . ' SPAM ' . $mailto . ' (' . $subject . ')');
			else
				write_log('markasjunk2', $uid . ' HAM ' . $mailto . ' (' . $subject . ')');

			if ($smtp_error['vars'])
				write_log('markasjunk2', $smtp_error['vars']);
		}
    }
}

?>