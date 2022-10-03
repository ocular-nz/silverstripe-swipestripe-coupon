<?php

namespace Coupon;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Security;
use SwipeStripe\Customer\Customer;
use SwipeStripe\Order\Modification;

class CouponModification extends Modification {

	private static $table_name = 'CouponModification';

	private static $has_one = array(
		'Coupon' => Coupon::class
	);

	private static $defaults = array(
		'SubTotalModifier' => false,
		'SortOrder' => 200
	);

	public function add($order, $value = null) 
	{
		//Get valid coupon for this order
		$code = Convert::raw2sql($value ?? $order->CouponCode);
		
		$coupon = $this->getValidCouponOrFail($order, $code);

		if (empty($coupon)) {
			return;
		}

		//Generate the Modification
		$mod = CouponModification::create();
		$mod->Price = $coupon->Amount($order)->getAmount();
		$mod->Currency = $coupon->Currency;
		$mod->Description = $coupon->Label();
		$mod->OrderID = $order->ID;
		$mod->Value = $coupon->ID;
		$mod->CouponID = $coupon->ID;
		$mod->write();
		
	}

	public function getValidCouponOrFail($order, $code): ?Coupon
	{
		$date = date('Y-m-d');

		$coupon = Coupon::get()
			->filter('Code', $code)
			->filter('Expiry:GreaterThanOrEqual', $date)
			->filter('MinimumSpend:LessThanOrEqual', $order->CartTotalPrice()->getAmount())
			->first();

		if (empty($coupon) || !$coupon->exists()) {
			return null;
		}

		if ($coupon->MaxCustomerUses > 0) {
			$orderIds = [];
			
			if (Customer::currentUser()) {
				$orderIds = Customer::currentUser()->Orders()
					->filter('PaymentStatus', 'Paid')
					->column('ID');
			}

			$count = 0;
			if (count($orderIds)) {
				$count = CouponModification::get()
					->filter('OrderID', $orderIds)
					->filter('CouponID', $coupon->ID)
					->count();
			}

			if ($count >= $coupon->MaxCustomerUses) {
				return null;
			}
		}

		return $coupon;
	}

	public function getFormFields() 
	{
		$fields = new FieldList();	

		$coupon = $this->Coupon();
		if ($coupon && $coupon->exists()) {

			$field = CouponModifierField::create($this, $coupon->Label(), $coupon->Code)
				->setAmount($coupon->Price($this->Order()));

			$fields->push($field);
		}

		return $fields;
	}
}
