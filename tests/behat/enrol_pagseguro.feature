@enrol @enrol_pagseguro
Feature: Users can auto-enrol themself in courses where pagseguro enrolment is allowed
  In order to participate in courses
  As a user
  I need to auto enrol me in courses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And I log in as "admin"
    And I navigate to "Manage enrol plugins" node in "Site administration > Plugins > Enrolments"
    And I click on "Enable" "link" in the "PagSeguro" "table_row"
    And I am on homepage
    And I follow "Courses"
    And I follow "Course 1"
    And I add "PagSeguro" enrolment method with:
      | Custom instance name | Test student enrolment |
      | Enrol cost           | 1                      |
    And I log out
    And I am on homepage

  # Note: Please keep the javascript tag on this Scenario to ensure that we
  # test use of the singleselect functionality.
  @javascript
  Scenario: PagSeguro enrolment enabled as guest
    When I follow "Courses"
    And I follow "Course 1"
    And I press "Log in as a guest"
    Then I should see "Guests cannot access this course. Please log in."
    And I press "Continue"
    And I should see "Log in"

  Scenario: PagSeguro enrolment enabled
    When I log in as "student1"
    And I am on site homepage
    And I follow "Courses"
    And I follow "Course 1"
    Then I should see "You must make a payment of BRL 1 via PagSeguro to access this course."
    And I should see "Send payment via PagSeguro"

  Scenario: PagSeguro-enrolment disabled
    When I log in as "student1"
    And I am on site homepage
    And I follow "Courses"
    And I follow "Course 1"
    Then I should see "You can not enrol yourself in this course"
