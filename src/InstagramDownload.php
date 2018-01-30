<?php
declare(strict_types=1);

namespace Ayesh\InstagramDownload;

class InstagramDownload {
  private $input_url;
  private $id;
  
  private $type = 'image';
  private $download_url;
  private $meta_values = array();
  
  private const INSTAGRAM_DOMAIN = 'instagram.com';

  /**
   * @param string $url
   *
   * @throws \InvalidArgumentException
   */
  public function __construct(string $url) {
    $this->setUrl($url);
  }

  /**
   * @param string $url
   *
   * @throws \InvalidArgumentException
   */
  private function setUrl(string $url): void {
    $id = $this->validateUrl($url);
    $this->id = $id;
    $this->input_url = $url;
  }

  /**
   * Returns the type of data: image or video.
   *
   * @return string
   * @throws \RuntimeException
   */
  public function getType(): string {
    if (!$this->download_url) {
      $this->process();
    }
    return $this->type;
  }
  
  public function getMeta(): string {
        if (!$this->download_url) {
            $this->process();
        }

        $fulltitle = $this->meta_values['og:title'];
        $bywho = explode("•", $fulltitle);


        return trim($bywho[0]);
    }

  /**
   * @param bool $force_dl
   *
   * @return string
   * @throws \RuntimeException
   */
  public function getDownloadUrl(bool $force_dl = TRUE): string {
    if (!$this->download_url) {
      $this->process();
    }

    if ($force_dl) {
      return $this->download_url . '?dl=1';
    }
    return $this->download_url;
  }

  /**
   *
   * @throws \RuntimeException
   */
  private function process(): void {
    $this->fetch($this->input_url);
    if (!\is_array($this->meta_values)) {
      throw new \RuntimeException('Error fetching information. Perhaps the post is private.', 3);
    }
    if (!empty($this->meta_values['og:video'])) {
      $this->type = 'video';
      $this->download_url = $this->meta_values['og:video'];
    }
    elseif (!empty($this->meta_values['og:image'])) {
      $this->type = 'image';
      $this->download_url = $this->meta_values['og:image'];
    }
    else {
      throw new \RuntimeException('Error fetching information. Perhaps the post is private.', 4);
    }
  }

  /**
   * @param string $url
   *
   * @return mixed
   * @throws \InvalidArgumentException
   */
  private function validateUrl($url) {
    $url = \parse_url($url);
    if (empty($url['host'])) {
      throw new \InvalidArgumentException('Invalid URL');
    }
    
    $url['host'] = \strtolower($url['host']);
    
    if ($url['host'] !== self::INSTAGRAM_DOMAIN && $url['host'] !== 'www.' . self::INSTAGRAM_DOMAIN) {
      throw new \InvalidArgumentException('Entered URL is not an ' . self::INSTAGRAM_DOMAIN . ' URL.');
    }

    if (empty($url['path'])) {
      throw new \InvalidArgumentException('No image or video found in this URL');
    }

    $args = \explode('/', $url['path']);
    if (!empty($args[1]) && $args[1] === 'p' && isset($args[2][4]) && !isset($args[2][255])) {
      return $args[2];
    }

    throw new \InvalidArgumentException('No image or video found in this URL');
  }

  private function fetch($URI) {
    $curl = \curl_init($URI);

    \curl_setopt($curl, \CURLOPT_FAILONERROR, true);
    \curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, true);
    \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
    \curl_setopt($curl, \CURLOPT_TIMEOUT, 15);

    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
      \curl_setopt($curl, \CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    }

    $response = \curl_exec($curl);

    \curl_close($curl);


    if(!empty($response)) {
      return $this->parse($response);
    }
    throw new \RuntimeException('Could not fetch data.');
  }

  private function parse($HTML) {
    $raw_tags = [];
    $this->meta_values = [];

    \preg_match_all('/<meta[^>]+="([^"]*)"[^>]' . '+content="([^"]*)"[^>]+>/i', $HTML, $raw_tags);

    if(!empty($raw_tags)) {
      $multi_value_tags = \array_unique(\array_diff_assoc($raw_tags[1], \array_unique($raw_tags[1])));
      foreach ($raw_tags[1] as $i => $tag) {
        $has_multiple_values = false;

        foreach($multi_value_tags as $multi_tag) {
          if($tag === $multi_tag) {
            $has_multiple_values = true;
          }
        }

        if($has_multiple_values) {
          $this->meta_values[$tag][] = $raw_tags[2][$i];
        }
        else {
          $this->meta_values[$tag] = $raw_tags[2][$i];
        }
      }
    }

    if (empty($this->meta_values)) {
      return false;
    }

    return $this->meta_values;
  }
}
