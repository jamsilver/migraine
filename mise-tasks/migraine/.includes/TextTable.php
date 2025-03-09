<?php

namespace Migraine;

/**
 * Creates a markdown document based on the parsed documentation.
 *
 * @author Peter-Christoph Haider <peter.haider@zeyon.net>
 * @package Apidoc
 * @version 1.00 (2014-04-04)
 * @license GNU Lesser Public License
 */
final class TextTable {

  /**
   * The source path.
   *
   * @var int
   */
  public $maxlen = 500;

  /**
   * The source path.
   *
   * @var array
   */
  private $data = [];

  /**
   * The source path.
   *
   * @var array
   */
  private $header = [];

  /**
   * The source path.
   *
   * @var array
   */
  private $len = [];

  /**
   * The source path.
   *
   * @var array
   */
  private $align = [
    'name' => 'L',
    'type' => 'C',
  ];

  /**
   * Constructor.
   *
   * @param array $header
   *   The header array [key => label, ...].
   * @param array $content
   *   Content.
   * @param array $align
   *   Alignment options [key => L|R|C, ...].
   */
  public function __construct($header = NULL, $content = [], $align = FALSE) {
    if ($header) {
      $this->header = $header;
    }
    elseif ($content) {
      foreach ($content[0] as $key => $value) {
        $this->header[$key] = $key;
      }
    }

    foreach ($this->header as $key => $label) {
      $this->len[$key] = strlen($label);
    }

    if (is_array($align)) {
      $this->setAlign($align);
    }

    $this->addData($content);
  }

  /**
   * Overwrite the alignment array.
   *
   * @param array $align
   *   Alignment options [key => L|R|C, ...].
   */
  public function setAlign($align) {
    $this->align = $align;
  }

  /**
   * Add data to the table.
   *
   * @param array $content
   *   Content.
   */
  public function addData($content) {
    foreach ($content as &$row) {
      foreach ($this->header as $key => $value) {
        if (!isset($row[$key])) {
          $row[$key] = '-';
        }
        elseif (strlen($row[$key]) > $this->maxlen) {
          $this->len[$key] = $this->maxlen;
          $row[$key] = substr($row[$key], 0, $this->maxlen - 3) . '...';
        }
        elseif (strlen($row[$key]) > $this->len[$key]) {
          $this->len[$key] = strlen($row[$key]);
        }
      }
    }

    $this->data = $this->data + $content;
    return $this;
  }

  /**
   * Add a delimiter.
   *
   * @return string
   *   The result.
   */
  private function renderDelimiter() {
    $res = '|';
    foreach ($this->len as $key => $l) {
      $res .= (isset($this->align[$key]) && ($this->align[$key] == 'C' || $this->align[$key] == 'L') ? ':' : ' ')
        . str_repeat('-', $l)
        . (isset($this->align[$key]) && ($this->align[$key] == 'C' || $this->align[$key] == 'R') ? ':' : ' ')
        . '|';
    }
    return $res . "\r\n";
  }

  /**
   * Render a single row.
   *
   * @param array $row
   *   The row.
   *
   * @return string
   *   The result.
   */
  private function renderRow($row) {
    $res = '|';
    foreach ($this->len as $key => $l) {
      $res .= ' ' . $row[$key] . ($l > strlen($row[$key]) ? str_repeat(' ', $l - strlen($row[$key])) : '') . ' |';
    }

    return $res . "\r\n";
  }

  /**
   * Render the table.
   *
   * @param array $content
   *   Additional table content.
   *
   * @return string
   *   The result.
   */
  public function render($content = []) {
    $this->addData($content);

    $res = $this->renderRow($this->header)
      . $this->renderDelimiter();
    foreach ($this->data as $row) {
      $res .= $this->renderRow($row);
    }

    return $res;
  }

}
