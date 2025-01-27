<?php
namespace YZhanTranslator;
use YZhanGateway\YZhanGateway;

class YZhanTranslator {
  private $yzhanGateway;
  private $apiUrl;
  private $client;

  public function __construct(?array $params = null) {
    $this->client       = $params['client'] ?? 'OpenAI';
    $this->yzhanGateway = new YZhanGateway($this->client, [
      'apiKey'       => $params['apiKey'],
      'organization' => $params['organization'],
    ]);
    $this->apiUrl = $params['apiUrl'];
  }

  public function run(array $content, string $input, ?array $params = []) {
    if (empty($params['prompt']) === false) {
      $content[0]['text'] .= '\n' . $params['prompt'];
    }

    $content[0]['text'] .= '\n' . $input;

    $params = array_merge([
      'method'     => 'POST',
      'url'        => $this->apiUrl . '/v1/chat/completions',
      'postFields' => [
        'model'    => 'gpt-4o-mini',
        'messages' => [['role' => 'user', "content" => $content]],
      ],
    ], $params);

    $cacheParams = $params['cache'] ?? ['type' => 'File', 'params' => []];
    $res         = $this->yzhanGateway->cache($cacheParams['cache']['type'] ?? 'File', $cacheParams['params'] ?? [])->request($params);

    $content = null;

    if (empty($res[1]['body']) === false) {
      $body = json_decode($res[1]['body'], true);

      if (empty($body['choices'][0]['message']['content']) === false) {
        $content = $body['choices'][0]['message']['content'];
      }
    }

    return [$content, $params];
  }

  public function translate(string $input, string $language, ?array $params = []) {
    if (isset($params['type'])) {
      if ($params['type'] === 'json') {
        list($content, $params) = $this->run([["type" => "text", "text" => 'Translate the values of the ' . $params['type'] . ' below into [' . $language . ']. Keep the keys unchanged, and output only the JSON string, without any markdown formatting.']], $input, $params);
      } elseif ($params['type'] === 'images') {
        $images                 = json_decode($input, true);
        list($content, $params) = $this->run(array_merge([
          ["type" => "text", "text" => 'Please describe the image using [' . $language . ']. Return in the format { "' . $images[0] . '": {"description": ""}, ...}. Only include descriptions, without markdown formatting.'],
        ], array_map(fn($image): array=> ["type" => 'image_url', "image_url" => ['url' => $image, "detail" => "high"]], $images)), '', $params);
      }
    } else {
      list($content, $params) = $this->run([["type" => "text", "text" => 'Translate the content below into [' . $language . '].']], $input, $params);
    }

    if (empty($content) === false) {
      if (isset($params['type'])) {
        if ($params['type'] === 'json' || $params['type'] === 'images') {
          $res = json_decode($content, true);
          if (empty($res) === false) {
            return $res;
          }
        }
      } else {
        return $content;
      }
    }

    $this->yzhanGateway->getCache()->delete($this->yzhanGateway->getKey($params));
    return null;
  }

  public function detect(string $input, ?array $languages, ?array $params = []) {
    list($content, $params) = $this->run([["type" => "text", "text" => 'Which of the following languages is the content in: ' . implode(',', $languages) . ', etc.? Output only one language name.']], $input, $params);

    if (empty($content) === false) {
      return $content;
    }

    $this->yzhanGateway->getCache()->delete($this->yzhanGateway->getKey($params));
    return null;
  }

  public function getClient() {
    return $this->client;
  }
}
?>