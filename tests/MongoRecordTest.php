<?php

/**
 * luuk. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class MongoRecordTest extends CTestCase
{

	private $fixturesInserting = [
		'call_id'     => 'some_call_id',
		'place_id'    => 'some_place_id',
		'device_type' => 'Computer',
	];

	private $fixturesUpdating = [
		'call_id'     => 'another_call_id',
		'place_id'    => 'another_place_id',
		'device_type' => 'Notebook',
	];

	public function testCreating()
	{
		$call = new ClubCall();
		$call->setAttributes($this->fixturesInserting);
		$call->created_at = new MongoDate();
		$call->updated_at = new MongoDate();

		$this->assertEquals('some_call_id', $call->call_id);
		$this->assertEquals('some_place_id', $call->place_id);
		$this->assertEquals('Computer', $call->device_type);
		$this->assertTrue($call->created_at instanceof MongoDate);
		$this->assertTrue($call->updated_at instanceof MongoDate);

		$this->assertTrue($call->save(false));

		$this->assertTrue($call->id instanceof MongoId);


		$this->removeModel($call);
	}

	private function removeModel(ClubCall $calls = null)
	{
		if ($calls !== null) {
			$calls->delete();
		}
	}

	/**
	 * @depends testCreating
	 */
	public function testUpdating()
	{
		$call = $this->insertModel();

		$this->assertTrue($call instanceof ClubCall, "Attempt to find call with id: " . var_export($call, true));

		$call->setAttributes($this->fixturesUpdating);
		$this->assertTrue($call->save(false));

		$this->assertEquals('another_call_id', $call->call_id);
		$this->assertEquals('another_place_id', $call->place_id);
		$this->assertEquals('Notebook', $call->device_type);

		$this->removeModel($call);

	}

	/**
	 * @return \ClubCall
	 */
	private function insertModel()
	{
		$call = new ClubCall();
		$call->setAttributes($this->fixturesInserting);
		$call->created_at = new MongoDate();
		$call->updated_at = new MongoDate();
		$call->save(false);

		return $call;
	}

	/**
	 * @depends testUpdating
	 */
	public function testFinding()
	{
		$call = $this->insertModel();
		$call->setAttributes($this->fixturesUpdating);
		$this->assertTrue($call->save(false));
		$id = $call->id;

		$call = null;

		$call = ClubCall::model()->findById($id);

		$this->assertEquals('another_call_id', $call->call_id);
		$this->assertEquals('another_place_id', $call->place_id);
		$this->assertEquals('Notebook', $call->device_type);
		$this->assertTrue($call->created_at instanceof MongoDate);
		$this->assertTrue($call->updated_at instanceof MongoDate);

		$this->removeModel($call);
	}

	/**
	 * @depends testFinding
	 */
	public function testDeleting()
	{
		$call = $this->insertModel();
		$callId = $call->id;
		$this->assertTrue($call instanceof ClubCall);

		$this->assertTrue($call->delete());
		$call = null;


		$call = ClubCall::model()->findById($callId);
		$this->assertFalse($call instanceof ClubCall);
	}
}