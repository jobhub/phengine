<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 09.10.14
 * Time: 13:58
 */

namespace Engine;


class Exchanger {


	protected $_handle = null;
  protected $_tid = 1;
  protected $_token = false;
  protected $_parse_headers = false;
  public $token_cookie = '*sid'; // если строка начинается с *, то будет использоваться
                                 // первая же кука, включающая в себя эту подстроку
                                 // (исключая символ *)

  public function __construct($url) {
    if (!function_exists('curl_init')) {
      throw new Exception("Отсутствует необходимое расширение: CURL");
    }
    $this->_handle = curl_init($url);
    if (!$this->_handle) {
      throw new Exception("Ошибка инициализации ресурса CURL");
    }
    curl_setopt($this->_handle, CURLOPT_POST, true);
    curl_setopt($this->_handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($this->_handle, CURLOPT_RETURNTRANSFER, true);
    if (function_exists('http_parse_headers')) {
      $this->_parse_headers = true;
    }
    curl_setopt($this->_handle, CURLOPT_HEADER, $this->_parse_headers);
    curl_setopt($this->_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=UTF-8'));

    // Без этой опции CURL не сохраняет куки между запросами
    // Но т.к. все запросы происходят в одном хендле курла, то в поле
    // можно ставить что угодно. Чтобы не логиниться каждый раз, можно добавить
    // еще и параметр CURLOPT_COOKIEFILE, дабы использовать одну сессию логина
    // при разных запросах
    // Также можно вообще опцию не указывать, т.к. куки токена ставятся автоматически,
    // а другие не нужны
    // curl_setopt($this->_handle, CURLOPT_COOKIEJAR, '/dev/null');
  }

  /**
   * Установить опцию http запроса (см. curl_setopt)
   * @param integer $opt
   * @param mixed $value
   * @return bool true при успехе, false при неудаче
   */
  public function setCurlOpt($opt, $value) {
    return curl_setopt($this->_handle, $opt, $value);
  }


  /**
   * Вызвать Ext.Direct метод, параметры вызова берутся из передаваемого массива
   *
   * @param string $action экшн
   * @param string $method метод
   * @param array|null $args Массив параметров, или NULL если параметры не нужны
   *   (в таких случаях допустим и пустой массив)
   * @return any результат DIRECT функции (null если там пусто)
   */
  public function request($args=null) {
    if (empty($args)) {
      $args = null;
    }

    curl_setopt($this->_handle, CURLOPT_POSTFIELDS, json_encode($args));
    $result = curl_exec($this->_handle);
    if (false===$result) {
      throw new Exception(curl_errno($this->_handle).": ".curl_error($this->_handle));
    }
    $code = curl_getinfo($this->_handle, CURLINFO_HTTP_CODE);
    if ($code>=400) {
      return array(
        'success' => false,
        'message' => "HTTP Error $code",
        'data' => $result,
      );
    }
    if ($this->_parse_headers) {
      $result = $this->parseHeaders($result);
    }

    /*$result = @json_decode($result, true);

    if ('exception'==$result['type']) {
      return array(
        'success' => false,
        'message' => $result['message'],
      );
    }
    if (!isset($result['result'])) {
      return null;
    }*/
    return $result;
  }

  /**
   * Изменение базового URL для запросов
   * @param type $url Новый URL
   */
  public function setUrl($url) {
    curl_setopt($this->_handle, CURLOPT_URL, $url);
  }

  /**
   * Вызвать Ext.Direct метод, изменив URL запросов. URL меняется и для последующих
   * запросов
   *
   * @param string $url URL для запроса
   * @param array|null $args Массив параметров, или NULL если параметры не нужны
   * @return any результат DIRECT функции (null если там пусто)
   */
  public function requestURL($url, $args=null) {
    $this->setUrl($url);
    return $this->request($args);
  }

  protected function parseCookies($headers) {
    $cookobjs = Array();

    foreach ($headers AS $k => $v) {
      if (strtolower($k) == "set-cookie") {
        if (!is_array($v)) {
          $v = array($v);
        }
        foreach ($v AS $k2 => $v2) {
          $cookobjs[] = http_parse_cookie($v2);
        }
      }
    }

    $cookies = Array();

    foreach ($cookobjs AS $row) {
      $cookies[] = $row->cookies;
    }

    $tmp = Array();

    ///sort k=>v format
    foreach ($cookies AS $v) {
      foreach ($v AS $k1 => $v1) {
        $tmp[$k1] = $v1;
      }
    }
    return $tmp;
  }

  protected function parseHeaders($response_body) {
    $separator = "\r\n\r\n";
    $end_headers = strpos($response_body, $separator);
    if (false===$end_headers) {
      return $response_body;
    }
    $headers = substr($response_body, 0, $end_headers);
    $response_body = substr($response_body, $end_headers + strlen($separator));
    if (trim($headers)=='HTTP/1.1 100 Continue') {
      return $this->parseHeaders($response_body);
    }
    $cookies = $this->parseCookies(http_parse_headers($headers));
    if ($this->token_cookie) {
      if ('*'==$this->token_cookie[0]) {
        $token = substr($this->token_cookie, 1);
        foreach ($cookies as $cookie=>$value) {
          if (false!==strpos($cookie, $token)) {
            $this->_token = $value;
            curl_setopt($this->_handle, CURLOPT_COOKIE, "{$cookie}={$this->_token}");
            break;
          }
        }
      } elseif (isset($cookies[$this->token_cookie])) {
        $this->_token = $cookies[$this->token_cookie];
        curl_setopt($this->_handle, CURLOPT_COOKIE, "{$this->token_cookie}={$this->_token}");
      }
    }
    return $response_body;
  }

	public static function sendPost($url, array $data, $header = "application/x-www-form-urlencoded") {
		// use key 'http' even if you send the request to https://...
		$options = array(
		    'http' => array(
		        'header'  => "Content-type: $header\r\n",
		        'method'  => 'POST',
		        'content' => http_build_query($data),
		    ),
		);
		$context  = stream_context_create($options);
		$resp = file_get_contents($url.'?XDEBUG_SESSION_START=18262', false, $context);
		return $resp;
	}
} 