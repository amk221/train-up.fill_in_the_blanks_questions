<?php
/*
Plugin Name: Train-up! Fill-in-the-blanks questions
Plugin URI: http://wptrainup.co.uk/
Description: Addon that enables a new type of question, for which trainees are required to fill in the blanks in a sentence
Author: @amk221
Version: 0.0.2
License: GPL2
*/

namespace TU;

class Fill_in_the_blanks_questions_addon {

  /**
   * $count_answers
   *
   * - A hash of question IDs and the current index of the blank.
   * - Keeps the count so that the shortcodes show the corresponding answer
   *
   * @var array
   *
   * @access private
   */
  private $count_answers = array();

  /**
   * $count_fields
   *
   * - A hash of question IDs and the current index of the blank.
   * - Keeps the count so that the shortcodes show the corresponding field
   *
   * @var array
   *
   * @access private
   */
  private $count_fields = array();

  /**
   * __construct
   *
   * Listen to the filters that the Train-Up! plugin provides, and latch on, 
   * inserting the new functionality where needed.
   * 
   * @access public
   */
  public function __construct() {
    $this->path = plugin_dir_path(__FILE__);

    add_shortcode('question_blank', array($this, '_shortcode_field'));
    add_filter('tu_question_types', array($this, '_add_type'));
    add_filter('tu_question_meta_boxes', array($this, '_add_meta_box'), 10, 2);
    add_action('tu_meta_box_fill_in_the_blanks', array($this, '_meta_box'));
    add_action('tu_save_question_fill_in_the_blanks', array($this, '_save_question'));
    add_filter('tu_render_question_fill_in_the_blanks', array($this, '_render_question'));
    add_filter('tu_render_answers_fill_in_the_blanks', array($this, '_render_answers'));
    add_filter('tu_validate_answer_fill_in_the_blanks', array($this, '_validate_answer'), 10, 3);
    add_filter('tu_question_title_fill_in_the_blanks', array($this, '_get_title'), 10, 2);
  }

  /**
   * _add_type
   *
   * - Callback for when retrieving the hash of question types. 
   * - Insert our new 'fill_in_the_blanks' question type.
   * 
   * @param mixed $types
   *
   * @access public
   *
   * @return array The altered types
   */
  public function _add_type($types) {
    $types['fill_in_the_blanks'] = __('Fill in the blanks', 'trainup');

    return $types;
  }

  /**
   * _add_meta_box
   *
   * - Callback for when the meta boxes are defined for Question admin screens
   * - Define one for our custom Question type: fill_in_the_blanks
   * 
   * @param mixed $meta_boxes
   *
   * @access public
   *
   * @return array The altered meta boxes
   */
  public function _add_meta_box($meta_boxes) {
    $meta_boxes['fill_in_the_blanks'] = array(
      'title'    => __('Fill in the blanks options', 'trainup'),
      'context'  => 'advanced',
      'priority' => 'default'
    );

    return $meta_boxes;
  }

  /**
   * _meta_box
   *
   * - Callback function for an action that is fired when the
   *   'fill_in_the_blanks' meta box is to be rendered.
   * - Echo out the view that tells the user how to use this Question Type.
   *   there is no functionality in this box.
   * 
   * @access public
   */
  public function _meta_box() {
    echo new View("{$this->path}/view/meta_box");
  }

  /**
   * _save_question
   *
   * - Fired when an fill_in_the_blanks question is saved
   * - Search the post's content for fill in the blank shortcodes.
   *   pull out the correct="" attribute which will contain the correct answer
   * - Update the Question's correct answer so that is can be validated.
   * 
   * @param mixed $question
   *
   * @access public
   */
  public function _save_question($question) {
    $answers = array();
    $pattern = get_shortcode_regex();

    preg_match_all("/{$pattern}/s", $question->post_content, $matches);

    if (isset($matches[2])) {
      foreach ($matches[2] as $i => $shortcode) {
        if ($shortcode === 'question_blank') {
          $attributes = shortcode_parse_atts($matches[3][$i]);
          $answer     = $attributes['correct'];

          array_push($answers, $answer);
        }
      }
    }

    update_post_meta($question->ID, 'tu_answers', $answers);
  }

  /**
   * _validate_answer
   *
   * - Fired when an fill_in_the_blanks question is validated
   * - Compare the array of answers that user entered, to the known blanks
   * 
   * @param mixed $correct Whether or not the answer is correct
   * @param mixed $users_answer The user's attempted answer
   * @param mixed $question The question this answer is for
   *
   * @access public
   *
   * @return boolean Whether or not the user answered correctly.
   */
  public function _validate_answer($correct, $users_answer, $question) {
    $correct        = true;
    $users_answers  = array_values((array)$users_answer);
    $actual_answers = array_values($question->get_answers());
    
    for ($i = 0, $l = count($actual_answers); $i < $l; $i++) {
      if ($users_answers[$i] != $actual_answers[$i]) {
        $correct = false;
        break;
      }
    }

    return $correct;
  }

  /**
   * _render_question
   *
   * When a Fill-in-the-blanks question is rendered, wrap it in a form, so that
   * the form inputs (the blanks) can get submitted.
   * 
   * @param mixed $content
   *
   * @access public
   *
   * @return string The altered content
   */
  public function _render_question($content) {
    $view = "{$this->path}/view/answers";
    $data = array(
      'content'    => tu()->question->post_content,
      'pagination' => tu()->question->pagination()
    );

    return new View($view, $data);
  }

  /**
   * _render_answers
   * 
   * @param mixed $content Description.
   *
   * @access public
   *
   * @return string Return a blank string, because Fill-in-the-blanks questions
   * do not need an answers form, because the post_content itself contains the
   * form inputs to fill in the blanks.
   */
  public function _render_answers($content) {
    return '';
  }

  /**
   * _get_title
   *
   * - Fired when the title of a Fill-in-the-blanks question is retreived
   * - Instead of returning just a small substring of the post content
   *   return the post content with the blanks filled. This is more useful.
   * - Temporarily override the question_blank shortcode so we can do 
   *   different swaps.
   * - Hack to pass the current question into the shortcode callback - use
   *   globals. Could use closures, but want to keep support good.
   * 
   * @param mixed $title
   * @param mixed $question
   *
   * @access public
   *
   * @return string The altered question title
   */
  public function _get_title($title, $question) {
    $context = &$this;

    $shortcode_answer = function($a, $c) use ($question, $context) {
      return $context->_shortcode_answer($a, $c, $question);
    };

    add_shortcode('question_blank', $shortcode_answer);

    $title = do_shortcode($question->post_content);

    add_shortcode('question_blank', array($this, '_shortcode_field'));

    return $title;
  }

  /**
   * _shortcode_answer
   *
   * - When a title of a fill-in-the-blanks question is retreived, shortcodes
   *   are run against it. Swap the shortcodes (fill in the blanks) with the
   *   user's answers. Or, if we are in the backend, we won't know the current
   *   user's answers so just show blanks.
   * - Note: This function is not your standard WordPress-callback for a 
   *   shortcode, it is fired from a closure which passes in the current 
   *   question.
   * 
   * @param array $attributes
   * @param string $content
   * @param object $question
   *
   * @access public
   *
   * @return string The correct answer or the user's answer
   */
  public function _shortcode_answer($attributes, $content, $question) {
    if (!isset($this->count_answers[$question->ID])) {
      $this->count_answers[$question->ID] = 0;
    }

    $blank = str_repeat('_', strlen($attributes['correct']));

    if (is_admin()) {
      $answer = $blank;
    } else {
      $i       = $this->count_answers[$question->ID];
      $answers = tu()->user->get_answer_to_question($question->ID);
      $answer  = !empty($answers[$i]) ? $answers[$i] : $blank;
      $answer  = "<b>{$answer}</b>";
    }

    $this->count_answers[$question->ID] += 1;

    return $answer;
  }

  /**
   * _shortcode_field
   *
   * - Callback for when a [question_blank correct=""] shortcode is used
   * - Load the current user's attempted answers to the current question
   * - Swap them in to the blanks so that they can change their mind.
   * 
   * @param mixed $attributes
   * @param mixed $content
   *
   * @access public
   *
   * @return string The form input that is the blank
   */
  public function _shortcode_field($attributes, $content) {
    $question_id = tu()->question->ID;

    if (!isset($this->count_fields[$question_id])) {
      $this->count_fields[$question_id] = 0;
    }

    $i       = $this->count_fields[$question_id];
    $answers = tu()->user->get_answer_to_question($question_id);
    $answer  = !empty($answers[$i]) ? $answers[$i] : '';
    $size    = strlen($attributes['correct']);

    $blank = "
      <span class='tu-question-blank'>
        <input type='text' class='tu-blank' name='tu_answer[]' size='{$size}' value='{$answer}'>
      </span>
    ";

    $this->count_fields[$question_id] += 1;

    return $blank;
  }

}

add_action('plugins_loaded', function() { 
  new Fill_in_the_blanks_questions_addon;
});