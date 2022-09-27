<?php

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Database\Live;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\RawSql;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Database\Seeds\CITestSeeder;

/**
 * @group DatabaseLive
 *
 * @internal
 */
final class UpdateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $seed    = CITestSeeder::class;

    public function testUpdateSetsAllWithoutWhere()
    {
        $this->db->table('user')->update(['name' => 'Bobby']);

        $result = $this->db->table('user')->get()->getResult();

        $this->assertSame('Bobby', $result[0]->name);
        $this->assertSame('Bobby', $result[1]->name);
        $this->assertSame('Bobby', $result[2]->name);
        $this->assertSame('Bobby', $result[3]->name);
    }

    public function testUpdateSetsAllWithoutWhereAndLimit()
    {
        try {
            $this->db->table('user')->update(['name' => 'Bobby'], null, 1);

            $result = $this->db->table('user')
                ->orderBy('id', 'asc')
                ->get()
                ->getResult();

            // this is really a bad test - indexes and other things can affect sort order
            if ($this->db->DBDriver === 'SQLSRV') {
                $this->assertSame('Derek Jones', $result[0]->name);
                $this->assertSame('Bobby', $result[1]->name);
            } else {
                $this->assertSame('Bobby', $result[0]->name);
                $this->assertSame('Ahmadinejad', $result[1]->name);
            }

            $this->assertSame('Richard A Causey', $result[2]->name);
            $this->assertSame('Chris Martin', $result[3]->name);
        } catch (DatabaseException $e) {
            // This DB doesn't support Where and Limit together
            // but we don't want it called a "Risky" test.
            $this->assertTrue(true);
        }
    }

    public function testUpdateWithWhere()
    {
        $this->db->table('user')->update(['name' => 'Bobby'], ['country' => 'US']);

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['name'] === 'Bobby') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    public function testUpdateWithWhereAndLimit()
    {
        try {
            $this->db->table('user')->update(['name' => 'Bobby'], ['country' => 'US'], 1);

            $result = $this->db->table('user')->get()->getResult();

            $this->assertSame('Bobby', $result[0]->name);
            $this->assertSame('Ahmadinejad', $result[1]->name);
            $this->assertSame('Richard A Causey', $result[2]->name);
            $this->assertSame('Chris Martin', $result[3]->name);
        } catch (DatabaseException $e) {
            // This DB doesn't support Where and Limit together
            // but we don't want it called a "Risky" test.
            $this->assertTrue(true);
        }
    }

    public function testUpdateBatch()
    {
        $data = [
            [
                'name'    => 'Derek Jones',
                'country' => 'Greece',
            ],
            [
                'name'    => 'Ahmadinejad',
                'country' => 'Greece',
            ],
        ];

        $this->db->table('user')->updateBatch($data, 'name');

        $this->seeInDatabase('user', [
            'name'    => 'Derek Jones',
            'country' => 'Greece',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Ahmadinejad',
            'country' => 'Greece',
        ]);
    }

    public function testUpdateWithWhereSameColumn()
    {
        $this->db->table('user')->update(['country' => 'CA'], ['country' => 'US']);

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['country'] === 'CA') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    public function testUpdateWithWhereSameColumn2()
    {
        // calling order: set() -> where()
        $this->db->table('user')
            ->set('country', 'CA')
            ->where('country', 'US')
            ->update();

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['country'] === 'CA') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    public function testUpdateWithWhereSameColumn3()
    {
        // calling order: where() -> set() in update()
        $this->db->table('user')
            ->where('country', 'US')
            ->update(['country' => 'CA']);

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['country'] === 'CA') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    /**
     * @group single
     *
     * @see   https://github.com/codeigniter4/CodeIgniter4/issues/324
     */
    public function testUpdatePeriods()
    {
        $this->db->table('misc')
            ->where('key', 'spaces and tabs')
            ->update([
                'value' => '30.192',
            ]);

        $this->seeInDatabase('misc', [
            'value' => '30.192',
        ]);
    }

    /**
     * @see https://codeigniter4.github.io/CodeIgniter4/database/query_builder.html#updating-data
     */
    public function testSetWithoutEscape()
    {
        $this->db->table('job')
            ->set('description', $this->db->escapeIdentifiers('name'), false)
            ->update();

        $this->seeInDatabase('job', [
            'name'        => 'Developer',
            'description' => 'Developer',
        ]);
    }

    public function testSetWithBoolean()
    {
        $this->db->table('type_test')
            ->set('type_boolean', false)
            ->update();

        $this->seeInDatabase('type_test', [
            'type_boolean' => false,
        ]);

        $this->db->table('type_test')
            ->set('type_boolean', true)
            ->update();

        $this->seeInDatabase('type_test', [
            'type_boolean' => true,
        ]);
    }

    public function testUpdateBatchTwoConstraints()
    {
        if (version_compare($this->db->getVersion(), '3.33.0') < 0) {
            $this->markTestSkipped('This SQLite version does not support this test.');
        }

        $data = [
            [
                'id'      => 1,
                'name'    => 'Derek Jones Changes',
                'country' => 'US',
            ],
            [
                'id'      => 2,
                'name'    => 'Ahmadinejad Does Not Change',
                'country' => 'Greece',
            ],
        ];

        $this->db->table('user')->updateBatch($data, 'id, country');

        $this->seeInDatabase('user', [
            'name'    => 'Derek Jones Changes',
            'country' => 'US',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Ahmadinejad',
            'country' => 'Iran',
        ]);
    }

    public function testUpdateBatchConstraintsRawSqlAndAlias()
    {
        if (version_compare($this->db->getVersion(), '3.33.0') < 0) {
            $this->markTestSkipped('This SQLite version does not support this test.');
        }

        $data = [
            [
                'id'      => 1,
                'name'    => 'Derek Jones Changes',
                'country' => 'US',
            ],
            [
                'id'      => 2,
                'name'    => 'Ahmadinejad Changes',
                'country' => 'Uruguay',
            ],
            [
                'id'      => 3,
                'name'    => 'Richard A Causey Changes',
                'country' => 'US',
            ],
            [
                'id'      => 4,
                'name'    => 'Chris Martin Does Not Change',
                'country' => 'Greece',
            ],
        ];

        $this->db->table('user')->setData($data, true, 'd')->updateBatch(
            null,
            ['id', new RawSql($this->db->protectIdentifiers('d')
            . '.' . $this->db->protectIdentifiers('country')
            . " LIKE 'U%'")]
        );

        $this->seeInDatabase('user', [
            'name'    => 'Derek Jones Changes',
            'country' => 'US',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Ahmadinejad Changes',
            'country' => 'Uruguay',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Richard A Causey Changes',
            'country' => 'US',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Chris Martin',
            'country' => 'UK',
        ]);
    }

    public function testUpdateBatchUpdateFieldsAndAlias()
    {
        if ($this->db->DBDriver === 'SQLite3' && ! (version_compare($this->db->getVersion(), '3.33.0') >= 0)) {
            $this->markTestSkipped('Only SQLite 3.33 and newer can complete this test.');
        }

        $data = [
            [
                'email'   => 'derek@world.com',
                'name'    => 'Derek Jones Does Not Change',
                'country' => 'Greece',
            ],
            [

                'email'   => 'ahmadinejad@world.com',
                'name'    => 'Ahmadinejad No change',
                'country' => 'Greece',
            ],
        ];

        $rawSql = new RawSql('CURRENT_TIMESTAMP');

        $updateFields = ['country', 'updated_at' => $rawSql];

        $this->db->table('user')->updateFields($updateFields)->onConstraint('email')->updateBatch($data);

        // check to see if update_at was updated
        $result = $this->db->table('user')
            ->where("email IN ('derek@world.com','ahmadinejad@world.com')")
            ->get()
            ->getResultArray();

        foreach ($result as $row) {
            $this->assertNotNull($row['updated_at']);
        }

        // only country and update_at should have changed
        $this->seeInDatabase('user', ['name' => 'Derek Jones', 'country' => 'Greece']);
        $this->seeInDatabase('user', ['name' => 'Ahmadinejad', 'country' => 'Greece']);

        // Original dataset from seeder
        $data = [
            [
                'name'    => 'Derek Should Change',
                'email'   => 'derek@world.com',
                'country' => 'Greece', // will update
            ],
            [
                'name'    => 'Ahmadinejad', // did't change above and will not change
                'email'   => 'ahmadinejad@world.com',
                'country' => 'Iran', // will not update
            ],
            [
                'name'    => 'Should Not Change',
                'email'   => 'richard@world.com',
                'country' => 'Greece', // will not update
            ],
            [
                'name'    => 'Should Change',
                'email'   => 'chris@world.com',
                'country' => 'UK', // will update
            ],
        ];

        $updateFields = ['name', 'updated_at' => new RawSql('NULL')];

        $esc = $this->db->escapeChar;

        // contraint is email and if the updated country = the source country
        // setting alias allows us to reference it in RawSql
        $this->db->table('user')
            ->updateFields($updateFields)
            ->onConstraint(['email', new RawSql("{$esc}db_user{$esc}.{$esc}country{$esc} = {$esc}_update{$esc}.{$esc}country{$esc}")])
            ->setAlias('_update')
            ->updateBatch($data);

        $result = $this->db->table('user')->get()->getResultArray();

        foreach ($result as $row) {
            if ($row['email'] === 'ahmadinejad@world.com') {
                $this->assertNotNull($row['updated_at']);
            } else {
                $this->assertNull($row['updated_at']);
            }
        }

        $this->seeInDatabase('user', ['name' => 'Derek Should Change', 'country' => 'Greece']);
        $this->seeInDatabase('user', ['name' => 'Ahmadinejad', 'country' => 'Greece']);
        $this->seeInDatabase('user', ['name' => 'Richard A Causey', 'country' => 'US']);
        $this->seeInDatabase('user', ['name' => 'Should Change', 'country' => 'UK']);
    }

    public function testUpdateBatchWithoutOnConstraint()
    {
        if ($this->db->DBDriver === 'SQLite3' && ! (version_compare($this->db->getVersion(), '3.33.0') >= 0)) {
            $this->markTestSkipped('Only SQLite 3.33 and newer can complete this test.');
        }

        $data = [
            [
                'name'    => 'Derek Nothing', // won't update
                'email'   => 'derek@world.com',
                'country' => 'Canada',
            ],
            [
                'name'    => 'Ahmadinejad',
                'email'   => 'ahmadinejad@world.com',
                'country' => 'Canada',
            ],
            [
                'name'    => 'Richard A Causey',
                'email'   => 'richard@world.com',
                'country' => 'Canada',
            ],
            [
                'name'    => 'Chris Martin',
                'email'   => 'chris@world.com',
                'country' => 'Canada',
            ],
        ];

        $this->db->table('user')->updateBatch($data, 'email, name', 2);

        $result = $this->db->table('user')->get()->getResultArray();

        foreach ($result as $row) {
            if ($row['email'] === 'derek@world.com') {
                $this->assertSame('US', $row['country']);
            } else {
                $this->assertSame('Canada', $row['country']);
            }
        }
    }

    public function testRawSqlConstraint()
    {
        if ($this->db->DBDriver === 'SQLite3' && ! (version_compare($this->db->getVersion(), '3.33.0') >= 0)) {
            $this->markTestSkipped('Only SQLite 3.33 and newer can complete this test.');
        }

        $data = [
            [
                'name'    => 'Derek Jones',
                'email'   => 'derek@world.com',
                'country' => 'Germany',
            ],
        ];

        $builder = $this->db->table('user');

        $builder->setData($data, true, 'db_myalias')
            ->updateFields('name, country')
            ->onConstraint(new RawSql($this->db->protectIdentifiers('user.email') . ' = ' . $this->db->protectIdentifiers('myalias.email')))
            ->updateBatch();

        $this->seeInDatabase('user', ['email' => 'derek@world.com', 'country' => 'Germany']);
    }
}
