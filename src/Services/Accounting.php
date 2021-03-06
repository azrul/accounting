<?php

namespace Scottlaurent\Accounting\Services;

use Scottlaurent\Accounting\Models\Journal;
use Money\Money;
use Money\Currency;

use Scottlaurent\Accounting\Exceptions\InvalidJournalEntryValue;
use Scottlaurent\Accounting\Exceptions\InvalidJournalMethod;
use Scottlaurent\Accounting\Exceptions\DebitsAndCreditsDoNotEqual;

use DB;

/**
 * Class Accounting
 * @package Scottlaurent\Accounting\Services
 */
class Accounting
{
	
	/**
	 * @var array
	 */
	protected $transctions_pending = [];
	
	/**
	 * @return Accounting
	 */
	public static function newDoubleEntryTransactionGroup()
    {
        return new self;
    }
	
	/**
	 * @param Journal $journal
	 * @param string $method
	 * @param Money $money
	 * @param string|null $memo
	 * @param null $referenced_object
	 * @throws InvalidJournalEntryValue
	 * @throws InvalidJournalMethod
	 * @internal param int $value
	 */
	function addTransaction(Journal $journal, string $method, Money $money, string $memo = null, $referenced_object = null) {
    	
    	if (!in_array($method,['credit','debit'])) {
    		throw new InvalidJournalMethod;
	    }
	    
    	if ($money->getAmount() <= 0) {
    		throw new InvalidJournalEntryValue();
	    }
	    
    	$this->transctions_pending[] = [
    	    'journal' => $journal,
		    'method' => $method,
		    'money' => $money,
		    'memo' => $memo,
		    'referenced_object' => $referenced_object
	    ];
    	
    }
	
	/**
	 * @param Journal $journal
	 * @param string $method
	 * @param $value
	 * @param string|null $memo
	 * @param null $referenced_object
	 */
	function addDollarTransaction(Journal $journal, string $method, $value, string $memo = null, $referenced_object = null) {
		$value = (int) ($value*100);
		$money = new Money($value, new Currency('USD'));
		$this->addTransaction($journal,$method,$money, $memo, $referenced_object);
    }
	
	/**
	 * @return array
	 */
	function getTransactionsPending() {
    	return $this->transctions_pending;
    }
	
	/**
	 *
	 */
	public function commit()
	{
		$this->verifyTransactionCreditsEqualDebits();

		try {
			
			foreach ($this->transctions_pending as $transction_pending) {
				$transaction = $transction_pending['journal']->{$transction_pending['method']}($transction_pending['money'],$transction_pending['memo']);
				if ($object = $transction_pending['referenced_object']) {
					$transaction->referencesObject($object);
				}
			}
			
			DB::commit();
			
		} catch (\Exception $e) {
			
			DB::rollBack();
			
			throw new TransactionCouldNotBeProcessed('Rolling Back Database. Message: ' . $e->getMessage());
		}
	}
	
	
	/**
	 *
	 */
	private function verifyTransactionCreditsEqualDebits()
	{
		$credits = 0;
		$debits = 0;
		
		foreach ($this->transctions_pending as $transction_pending) {
			if ($transction_pending['method']=='credit') {
				$credits += $transction_pending['money']->getAmount();
			} else {
				$debits += $transction_pending['money']->getAmount();
			}
		}
		
		if ($credits !== $debits) {
			throw new DebitsAndCreditsDoNotEqual('In this transaction, credits == ' . $credits  . ' and debits == ' . $debits);
		}
	}
	
	
}