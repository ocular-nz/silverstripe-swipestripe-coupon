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
		if($value !== null){
			$code = Convert::raw2sql($value);	
		}else{
			$code = Convert::raw2sql($order->CouponCode);		
		}
		
		$date = date('Y-m-d');

		$coupon = Coupon::get()
			->filter('Code', $code)
			->filter('Expiry:GreaterThanOrEqual', $date)
			->filter('MinimumSpend:LessThanOrEqual', $order->CartTotalPrice()->getAmount())
			->first();

		if (empty($coupon) || !$coupon->exists()) {
			return;
		}

		if ($coupon->MaxCustomerUses > 0) {
			$orderIds = Customer::currentUser()->Orders()
				->filter('PaymentStatus', 'Paid')
				->column('ID');

			$count = 0;
			if (count($orderIds)) {
				$count = CouponModification::get()
					->filter('OrderID', $orderIds)
					->filter('CouponID', $coupon->ID)
					->count();
			}

			if ($count >= $coupon->MaxCustomerUses) {
				return;
			}
		}

		//Generate the Modification
		$mod = new CouponModification();
		$mod->Price = $coupon->Amount($order)->getAmount();
		$mod->Currency = $coupon->Currency;
		$mod->Description = $coupon->Label();
		$mod->OrderID = $order->ID;
		$mod->Value = $coupon->ID;
		$mod->CouponID = $coupon->ID;
		$mod->write();
		
	}

	public function getFormFields() {

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
