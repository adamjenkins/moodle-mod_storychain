@mod @mod_storychain
Feature: Writing a story one sentence at a time
  In order to write a story together
  As a student
  I want to add a sentence when it is my turn, seeing only the sentence before mine

  Background:
    Given the following "courses" exist:
      | fullname | shortname | summary |
      | Writing  | WRI1      |         |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tess      | Teacher  |
      | student1 | Ken       | Ito      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | WRI1   | editingteacher |
      | student1 | WRI1   | student        |
    And the following "activities" exist:
      | activity   | name         | course | idnumber | openingmode | openings             | titles        |
      | storychain | Spooky tales | WRI1   | chain1   | 1           | It was a dark night. | The old house |

  @javascript
  Scenario: A student writes a sentence and the teacher sees it in the live view
    Given I am on the "Spooky tales" "storychain activity" page logged in as "student1"
    Then I should see "It's your turn!"
    And I should see "It was a dark night."
    And I should see "The old house"
    And I set the field "Your sentence" to "A door creaked open."
    And I press "Add my sentence"
    And I should see "It's your turn!"
    And I should see "A door creaked open."
    And I should not see "It was a dark night."
    And I log out
    And I am on the "Spooky tales" "storychain activity" page logged in as "teacher1"
    And I should see "A door creaked open."
    And I should see "It was a dark night."
    And I should see "Being written"
    And I click on "Close" "button" in the ".mod-storychain-teacherchain" "css_element"
    And I should see "Finished"
    And I should see "Reveal to students"
