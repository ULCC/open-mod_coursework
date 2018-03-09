<?php
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use mod_coursework\models\user;

/**
 * This forms a common base for the page context objects that are used to hold the functions
 * that the behat steps call on. The pattern is to have one class to represent one page in the
 * application. The functions can be reused in different steps and any common stuff that all pages
 * use lives in this base class.
 */
class mod_coursework_behat_page_base {

    /**
     * @var behat_mod_coursework
     */
    protected $context;

    /**
     * Constructor stores the session for use later when the page API functions are needed.
     *
     * @param behat_mod_coursework $context
     */
    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * @return \Behat\Mink\Session
     */
    protected function getSession() {
        return $this->context->getSession();
    }

    /**
     * @return behat_mod_coursework
     */
    protected function getContext() {
        return $this->context;
    }

    /**
     * @return \Behat\Mink\Element\DocumentElement
     */
    protected function getPage() {
        return $this->getSession()->getPage();
    }

    /**
     * Checks whether the page has the text anywhere on it.
     *
     * Pulled from behat_general
     *
     * @param string $text
     * @return bool
     * @throws Behat\Mink\Exception\ExpectationException
     */
    public function should_have_text($text) {

        $page_text = $this->getPage()->getText();
        if (substr_count($page_text, $text)== 0) {
            throw new ExpectationException('Page did not have text "'.$text.'"', $this->getSession());
        }

    }

    /**
     * @param string $css
     * @param string $text
     * @param string $error
     */
    protected function should_have_css($css, $text = '', $error = '') {
        $elements = $this->getPage()->findAll('css', $css);
        assertGreaterThanOrEqual(1, count($elements), $error);
        if ($text) {
            $actual_text = reset($elements)->getText();
            assertContains($text, $actual_text, $error);
        }
    }

    /**
     * @param string $css
     * @param string $text
     */
    protected function should_not_have_css($css, $text = '') {
        $elements = $this->getPage()->findAll('css', $css);
        if ($text) {
            foreach ($elements as $element) {
                $actual_text = $element->getText();
                assertNotContains($text, $actual_text);
            }
        } else {
            assertEquals(0, count($elements));
        }
    }

    /**
     * @param $thing_css
     * @param string $text
     * @throws ExpectationException
     * @throws \Behat\Mink\Exception\ElementException
     */
    protected function click_that_thing($thing_css, $text = '') {
        $ok = false;
        /**
         * @var $things NodeElement[]
         */
        $things = $this->getPage()->findAll('css', $thing_css);
        foreach($things as $thing) {
            if (empty($text) || $thing->getText() == $text || $thing->getValue() == $text) {
                $thing->click();
                $ok = true;
                break;
            }
        }

        if (empty($ok)) {
            $message = 'Tried to click a thing that is not there: ' . $thing_css. ' '. $text;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param string $thing_css
     * @param string $text
     * @return bool
     */
    protected function has_that_thing($thing_css, $text = '') {
        $found_it = false;
        /**
         * @var $things NodeElement[]
         */
        $things = $this->getPage()->findAll('css', $thing_css);
        foreach ($things as $thing) {
            if (empty($text) || $thing->getText() == $text || $thing->getValue() == $text) {
                $found_it = true;
                break;
            }
        }

        return $found_it;
    }

    /**
     * @param $allocatable
     * @return string
     */
    protected function allocatable_identifier_hash($allocatable) {
        return $this->getContext()->coursework->get_allocatable_identifier_hash($allocatable);
    }

    /**
     * @param string $field_name
     * @param int $timestamp
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    protected function fill_in_date_field($field_name, $timestamp) {
        // Select the date from the dropdown
        $minute_dropdown_selector = "id_{$field_name}_minute";
        $hour_dropdown_selector = "id_{$field_name}_hour";
        $day_dropdown_selector = "id_{$field_name}_day";
        $month_dropdown_selector = "id_{$field_name}_month";
        $year_dropdown_selector = "id_{$field_name}_year";

        $minute = date('i', $timestamp);
        $hour = date('H', $timestamp);
        $day = date('j', $timestamp);
        $month = date('n', $timestamp);
        $year = date('Y', $timestamp);

        $this->getPage()->fillField($minute_dropdown_selector, $minute);
        $this->getPage()->fillField($hour_dropdown_selector, $hour);
        $this->getPage()->fillField($day_dropdown_selector, $day);
        $this->getPage()->fillField($month_dropdown_selector, $month);
        $this->getPage()->fillField($year_dropdown_selector, $year);
    }

    /**
     * @param string $locator xpath
     * @throws ElementNotFoundException
     */
    protected function pressButtonXpath($locator) {
        // behat generates button type submit whereas code does input
        $inputtype = $this->getPage()->find('xpath', $locator ."//input[@type='submit']");
        $buttontype = $this->getPage()->find('xpath',  $locator ."//button[@type='submit']");

        $button = ($inputtype !== null)? $inputtype : $buttontype;// check how element was created and use it to find the button

        if (null === $button) {
            throw new ElementNotFoundException(
                $this->getSession(), 'button', 'xpath', $button->getXpath()
            );
        }

        $button->press();
    }
}