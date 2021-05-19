<?php
define('IMG_DIR' , './');
define('MAIL_DIR', './');
define('FILE_CODE', mb_internal_encoding());

/**
 * メール処理クラス
 *
 * 受信したメールを解析
 *
 * @package jp.takaya
 * @access  public
 * @author  Shuichi Takaya
 * @create  2007/11/01
 * @version $Id:$
 **/
class Mailer {

  /**
   * <code>subject</code> サブジェクト
   */
  var $subject = '';

  /**
   * <code>from</code> 差出人メールアドレス
   */
  var $from = '';

  /**
   * <code>date</code> 送信日付
   */
  var $date = '';

  /**
   * <code>data</code> メールデータ
   */
  var $data = '';

  /**
   * コンストラクタ
   */
  function Mailer() {
	// 標準入力からデータ取得
	$this->data = $this->readMailFromStdin();
  }

  /**
   * メールデータパース処理
   * @access   public
   * @return   Array   メールボディ
   */
  function parseMail() {
	list($head, $body) = $this->splitForHeadAndBody($this->data);

	// 日付の抽出
	preg_match("/Date:[ \t]*([^\r\n]+)/i", $head, $temp = '');
	$this->date = $temp[1];
	$head = preg_replace("/\r\n? /", '', $head);

	// サブジェクトの抽出
	if (preg_match("/\nSubject:[ \t]*([^\r\n]+)/i", $head, $temp)) {
	  $this->subject = $temp[1];

	  // MIME Bデコード
	  while (preg_match("/(.*)=\?iso-2022-jp\?B\?([^?]+)\?=(.*)/i", $this->subject, $temp)) {
		$this->subject = $temp[1].base64_decode($temp[2]).$temp[3];
	  }

	  // MIME Qデコード
	  while (preg_match("/(.*)=\?iso-2022-jp\?Q\?([^?]+)\?=(.*)/i", $this->subject, $temp)) {
		$this->subject = $temp[1].quoted_printable_decode($temp[2]).$temp[3];
	  }
	  $this->subject = htmlspecialchars(mb_convert_encoding($this->subject, FILE_CODE, 'JIS,SJIS'));
	}

	// 送信者アドレスの抽出
	if (preg_match("/From:[ \t]*([^\r\n]+)/i", $head, $temp)) {
	  $this->from = $this->getMailAddress($temp[1]);
	} else if (preg_match("/Reply-To:[ \t]*([^\r\n]+)/i", $head, $temp)) {
	  $this->from = $this->getMailAddress($temp[1]);
	} else if (preg_match("/Return-Path:[ \t]*([^\r\n]+)/i", $head, $temp)) {
	  $this->from = $this->getMailAddress($temp[1]);
	}

	// マルチパートの場合
	if (preg_match("/\nContent-type:.*multipart\//i", $head)) {
	  preg_match('/boundary="([^"]+)"/i', $head, $temp);
	  $body = str_replace($temp[1], urlencode($temp[1]), $body);
	  $part = explode("/\r\n--" . urlencode($temp[1]) . "-?-?/", $body);
	} else {
	  // テキストメール
	  $part[0] = $this->data;
	}

	return $part;
  }

  /**
   * メールデータを出力する
   * @access   public
   */
  function outputData() {

	// マルチパートを処理
	foreach ($this->parseMail() as $multi) {
	  list($head, $body) = $this->splitForHeadAndBody($multi);
	  $body = preg_replace("/\r\n\.\r\n$/", '', $body);

	  // コンテンツタイプが取得できない場合
	  if (!preg_match("/Content-type: *([^;\n]+)/i", $head, $temp)) {
		continue;
	  }

	  // コンテンツタイプを格納
	  list($main, $type) = explode('/', $temp[1]);

	  // テキストの場合
	  if (strtolower($main) == 'text') {

		// ベース64の場合
		if (preg_match("/Content-Transfer-Encoding:.*base64/i", $head)) {
		  $body = base64_decode($body);
		}

		// クオーテッドプリンタブルの場合
		if (preg_match("/Content-Transfer-Encoding:.*quoted-printable/i", $head)) {
		  $body = quoted_printable_decode($body);
		}

		$text = mb_convert_encoding($body, FILE_CODE, 'JIS,SJIS');

		// HTMLの場合
		if ($type == 'html') {
		  // 全てのタグを削る
		  $text = strip_tags($text);
		} else {
		  $text = htmlspecialchars($text);
		}
	  }

	  // ファイル名を抽出
	  if (preg_match("/name=\"?([^\"\n]+)\"?/i", $head, $temp)) {
		$filename = preg_replace("/[\t\r\n]/", '', $temp[1]);
		while (preg_match("/(.*)=\?iso-2022-jp\?B\?([^\?]+)\?=(.*)/i", $filename, $temp)) {
		  $filename = $temp[1] . base64_decode($temp[2]) . $temp[3];
		  $filename = strtotime($this->date) . mb_convert_encoding($filename, FILE_CODE, 'JIS,SJIS');
		}
	  }

	  // 結果出力
	  echo $text;
	}
  }

  /**
   * メール添付ファイルをデコードして保存する
   * @access public
   */
  function saveAttacheFile() {

	// マルチパートを処理
	foreach ($this->parseMail() as $multi) {
	  list($head, $body) = $this->splitForHeadAndBody($multi);
	  $body = preg_replace("/\r\n\.\r\n$/", '', $body);

	  // コンテンツタイプが取得できない場合
	  if (!preg_match("/Content-type: *([^;\n]+)/i", $head, $temp)) {
		continue;
	  }

	  // コンテンツタイプを格納
	  $type = explode('/', $temp[1]);
	  $type = $type[1];

	  // ファイル名を抽出
	  if (preg_match("/name=\"?([^\"\n]+)\"?/i", $head, $temp)) {
		$filename = preg_replace("/[\t\r\n]/", '', $temp[1]);
		while (preg_match("/(.*)=\?iso-2022-jp\?B\?([^\?]+)\?=(.*)/i", $filename, $temp)) {
		  $filename = $temp[1] . base64_decode($temp[2]) . $temp[3];
		  $filename = mb_convert_encoding($filename, FILE_CODE, 'JIS,SJIS');
		}
	  }

	  // 添付ファイルをデコードして保存
	  if (preg_match("/Content-Transfer-Encoding:.*base64/i", $head)
		&&  preg_match('/gif|jpeg|png|bmp|mpeg|3gpp|asf/i', $type)) {
		$temp = base64_decode($body);
		$filename = strtotime($this->date) . $filename;
		$fp = fopen(IMG_DIR . $filename, 'wb');
		fputs($fp, $temp);
		fclose($fp);
	  }
	}
  }

  /**
   * ヘッダと本文を分割する
   * @access   public
   * @param    $data   String    メール本文のデータ
   * @return   Array   処理結果
   */
  function splitForHeadAndBody($data) {
	$temp = explode("\r\n\r\n", $data, 2);
	$temp[1] = preg_replace("/\r\n[\t ]+/", ' ', $temp[1]);
	return $temp;
  }

  /**
   * メールアドレスを抽出する
   * @access   public
   * @param    $data    String    メールヘッダデータ
   * @return   String   メールアドレス
   */
  function getMailAddress($data) {

	// メールアドレスの正規表現
	$pattern = "/[-!#$%&\'*+\\.0-9A-Z^_`a-z{|}~]+@"   // アカウント
	  . "[-!#$%&\'*+\\0-9=?A-Z^_`a-z{|}~]+\." // サブドメイン
	  . "[-!#$%&\'*+\\.0-9=?A-Z^_`a-z{|}~]+/"; // ドメイン

	// メールアドレスを調べる
	if (preg_match($pattern, $data, $temp)) {
	  return $temp[0];
	}

	return '';
  }

  /**
   * 標準入力からメールデータを受け取る
   * @access public
   * @return String 処理結果
   */
  function readMailFromStdin() {

	// 標準入力からデータ取得
	$stdin = fopen('php://stdin', 'r');

	// 最終行まで読む
	$data = '';
	while (!feof($stdin)) {
	  $data .= fgets($stdin);
	}

	// クローズ
	fclose($stdin);

	return $data;
  }

  /**
   * メールディレクトリから最後のメールを取り出す
   * @access public
   * @return String メール本文
   */
  function readMailFromLastFile() {
	// ディレクトリチェック
	if (is_dir(MAIL_DIR)) {
	  return '';
	}

	// ディレクトリオープン
	$handle = @opendir(MAIL_DIR);

	// ディレクトリチェック
	if (!$handle) {
	  return '';
	}

	// 最後に更新されたファイルを探す
	$lastTime = 0;
	while (($file = readdir($handle)) !== false) {

	  // カレントディレクトリは除く
	  if ($file == '.' || $file == '..') {
		continue;
	  }

	  // 最後に更新されたファイルを探す
	  $fileTime = filemtime(MAIL_DIR . $file);


	  if ($lastTime < $fileTime) {
		$lastTime = $fileTime;
		$lastFile = MAIL_DIR . $file;
	  }
	}

	// ディレクトリクローズ
	closedir($handle);

	// ファイルオープン
	$fp = fopen($lastFile, 'r');
	if (!$fp) {
	  return '';
	}

	// ファイルの内容を格納
	$data = fgets($fp, filesize($lastFile));
	fclose($fp);

	// データを返却
	return $data;
  }
}

$mailer = new Mailer();

// 添付ファイルをセーブ
$mailer->saveAttacheFile();

// データを出力
$mailer->outputData();

?>
