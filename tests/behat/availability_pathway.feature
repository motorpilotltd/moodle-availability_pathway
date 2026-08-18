@availability @availability_pathway @javascript
Feature: Restrict access by pathway choice
  In order to open up branches of a course
  As a teacher
  I need to restrict activities by the selection made in a pathway activity

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name              | idnumber | options   |
      | pathway  | C1     | Choose your route | PW1      | Red, Blue |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | page     | C1     | Secret page | PAGE1    |

  Scenario: A student gains access to a restricted activity by choosing the right option
    Given I am on the "PAGE1" "Activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I press "Add restriction..."
    And I click on "Pathway" "button" in the "Add restriction..." "dialogue"
    And I set the field "Pathway activity" to "Choose your route"
    And I set the field "Required option" to "Red"
    And I press "Save and return to course"
    When I am on the "C1" "Course" page logged in as "student1"
    Then I should see "Not available unless: You chose Red in Choose your route"
    When I am on the "PW1" "Activity" page
    And I click on "Red" "radio"
    And I press "Save choice"
    And I am on the "C1" "Course" page
    Then "Secret page" "link" should exist
    And I should not see "Not available unless"

  Scenario: The restriction is not offered when the course has no pathway with options
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C2     | editingteacher |
    And the following "activities" exist:
      | activity | course | name       | idnumber |
      | page     | C2     | Plain page | PAGE2    |
    When I am on the "PAGE2" "Activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I press "Add restriction..."
    Then "Pathway" "button" should not exist in the "Add restriction..." "dialogue"
