<?php
/**
 * This file contains only the EditCounterTest class.
 */

namespace Tests\Xtools;

use Xtools\EditCounter;
use Xtools\EditCounterRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;
use DateTime;

/**
 * Tests for the EditCounter.
 */
class EditCounterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Get counts of revisions: deleted, not-deleted, and total.
     */
    public function testLiveAndDeletedEdits()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getPairData')
            ->willReturn([
                'deleted' => 10,
                'live' => 100,
            ]);

        $project = new Project('TestProject');
        $user = new User('Testuser');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);

        $this->assertEquals(100, $editCounter->countLiveRevisions());
        $this->assertEquals(10, $editCounter->countDeletedRevisions());
        $this->assertEquals(110, $editCounter->countAllRevisions());
    }

    /**
     * A first and last date, and number of days between.
     */
    public function testDates()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())->method('getPairData')->willReturn([
                'first' => '20170510100000',
                'last' => '20170515150000',
            ]);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);
        $this->assertEquals(
            new \DateTime('2017-05-10 10:00'),
            $editCounter->datetimeFirstRevision()
        );
        $this->assertEquals(
            new \DateTime('2017-05-15 15:00'),
            $editCounter->datetimeLastRevision()
        );
        $this->assertEquals(5, $editCounter->getDays());
    }

    /**
     * Only one edit means the dates will be the same.
     */
    public function testDatesWithOneRevision()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getPairData')
            ->willReturn([
                'first' => '20170510110000',
                'last' => '20170510110000',
            ]);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $editCounter->datetimeFirstRevision()
        );
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $editCounter->datetimeLastRevision()
        );
        $this->assertEquals(1, $editCounter->getDays());
    }

    /**
     * Test that page counts are reported correctly.
     */
    public function testPageCounts()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getPairData')
            ->willReturn([
                'edited-live' => '3',
                'edited-deleted' => '1',
                'created-live' => '6',
                'created-deleted' => '2',
            ]);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);

        $this->assertEquals(3, $editCounter->countLivePagesEdited());
        $this->assertEquals(1, $editCounter->countDeletedPagesEdited());
        $this->assertEquals(4, $editCounter->countAllPagesEdited());

        $this->assertEquals(6, $editCounter->countCreatedPagesLive());
        $this->assertEquals(2, $editCounter->countPagesCreatedDeleted());
        $this->assertEquals(8, $editCounter->countPagesCreated());
    }

    /**
     * Test that namespace totals are reported correctly.
     */
    public function testNamespaceTotals()
    {
        $namespaceTotals = [
            // Namespace IDs => Edit counts
            '1' => '3',
            '2' => '6',
            '3' => '9',
            '4' => '12',
        ];
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getNamespaceTotals')
            ->willReturn($namespaceTotals);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);

        $this->assertEquals($namespaceTotals, $editCounter->namespaceTotals());
    }

    /**
     * Test that month counts are properly put together.
     */
    public function testMonthCounts()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getMonthCounts')
            ->willReturn([
                [
                    'year' => '2016',
                    'month' => '12',
                    'page_namespace' => '0',
                    'count' => '10',
                ],
                [
                    'year' => '2017',
                    'month' => '3',
                    'page_namespace' => '0',
                    'count' => '20',
                ],
                [
                    'year' => '2017',
                    'month' => '2',
                    'page_namespace' => '1',
                    'count' => '50',
                ],
            ]);
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getRegistrationDate')
            ->willReturn('20161105000000');
        $projectRepo = $this->getMock(ProjectRepository::class);

        $project = new Project('TestProject');
        $project->setRepository($projectRepo);
        $user = new User('Testuser1');
        $user->setRepository($userRepo);
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);

        // Mock current time by passing it in (dummy parameter, so to speak).
        $monthCounts = $editCounter->monthCounts(new DateTime('2017-04-30 23:59:59'));

        // Make sure zeros were filled in for months with no edits,
        //   and for each namespace.
        $this->assertArraySubset(
            [
                201611 => 0,
                201612 => 10,
                20171 => 0,
                20172 => 0,
                20173 => 20,
                20174 => 0,
            ],
            $monthCounts['totals'][0]
        );
        $this->assertArraySubset(
            [
                201611 => 0,
                201612 => 0,
                20171 => 0,
                20172 => 50,
                20173 => 0,
                20174 => 0,
            ],
            $monthCounts['totals'][1]
        );

        // Assert that only active namespaces are reported.
        $this->assertEquals([0, 1], array_keys($monthCounts['totals']));

        // Sort keys so we can more easily test.
        ksort($monthCounts['totals'][0]);

        // Assert that only active months, leading up to the current, are reported.
        // Note that treated as integers, the ksort above puts 2016's data after 2017's.
        $this->assertEquals(
            [20171, 20172, 20173, 20174, 201611, 201612],
            array_keys($monthCounts['totals'][0])
        );

        $this->assertEquals('2016', $monthCounts['min_year']);
        $this->assertEquals('2017', $monthCounts['max_year']);
        $this->assertEquals('11', $monthCounts['min_month']);
        $this->assertEquals('4', $monthCounts['max_month']);
    }

    /**
     * Get all global edit counts, or just the top N, or the overall grand total.
     */
    public function testGlobalEditCounts()
    {
        $wiki1 = new Project('wiki1');
        $wiki2 = new Project('wiki2');
        $editCounts = [
            ['project' => new Project('wiki0'), 'total' => 30],
            ['project' => $wiki1, 'total' => 50],
            ['project' => $wiki2, 'total' => 40],
            ['project' => new Project('wiki3'), 'total' => 20],
            ['project' => new Project('wiki4'), 'total' => 10],
            ['project' => new Project('wiki5'), 'total' => 35],
        ];
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('globalEditCounts')
            ->willReturn($editCounts);
        $user = new User('Testuser1');
        $editCounter = new EditCounter($wiki1, $user);
        $editCounter->setRepository($editCounterRepo);

        // Get the top 2.
        $this->assertEquals(
            [
                ['project' => $wiki1, 'total' => 50],
                ['project' => $wiki2, 'total' => 40],
            ],
            $editCounter->globalEditCountsTopN(2)
        );

        // And the bottom 4.
        $this->assertEquals(95, $editCounter->globalEditCountWithoutTopN(2));

        // Grand total.
        $this->assertEquals(185, $editCounter->globalEditCount());
    }

    /**
     * Ensure parsing of log_params properly works, based on known formats
     */
    public function testLongestBlockDays()
    {
        $wiki = new Project('wiki1');
        $user = new User('Testuser1');

        // Scenario 1
        $editCounter = new EditCounter($wiki, $user);
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounter->setRepository($editCounterRepo);
        $editCounterRepo->expects($this->once())
            ->method('getBlocksReceived')
            ->with($wiki, $user)
            ->willReturn([
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"72 hours";s:8:"6::flags";s:8:"nocreate";}',
                ],
                [
                    'log_timestamp' => '20170301000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"1 month";s:8:"6::flags";s:11:"noautoblock";}',
                ],
            ]);
        $this->assertEquals(31, $editCounter->getLongestBlockDays());

        // Scenario 2
        $editCounter2 = new EditCounter($wiki, $user);
        $editCounterRepo2 = $this->getMock(EditCounterRepository::class);
        $editCounter2->setRepository($editCounterRepo2);
        $editCounterRepo2->expects($this->once())
            ->method('getBlocksReceived')
            ->with($wiki, $user)
            ->willReturn([
                [
                    'log_timestamp' => '20170201000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"infinite";s:8:"6::flags";s:8:"nocreate";}',
                ],
                [
                    'log_timestamp' => '20170701000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"60 days";s:8:"6::flags";s:8:"nocreate";}',
                ],
            ]);
        $this->assertEquals(-1, $editCounter2->getLongestBlockDays());

        // Scenario 3
        $editCounter3 = new EditCounter($wiki, $user);
        $editCounterRepo3 = $this->getMock(EditCounterRepository::class);
        $editCounter3->setRepository($editCounterRepo3);
        $editCounterRepo3->expects($this->once())
            ->method('getBlocksReceived')
            ->with($wiki, $user)
            ->willReturn([
                [
                    'log_timestamp' => '20170701000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"60 days";s:8:"6::flags";s:8:"nocreate";}',
                ],
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "9 weeks\nnoautoblock",
                ],
            ]);
        $this->assertEquals(63, $editCounter3->getLongestBlockDays());
    }
}
