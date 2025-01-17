<?php

namespace Coupon;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\View\Requirements;
use SwipeStripe\Customer\Cart;
use SwipeStripe\Form\ModificationField_Hidden;

/**
 * Form field that represents {@link CouponRate}s in the Checkout form.
 */
class CouponModifierField extends ModificationField_Hidden
{

	/**
	 * The amount this field represents e.g: 15% * order subtotal
	 * 
	 * @var DBMoney
	 */
	protected $amount;

	/**
	 * Render field with the appropriate template.
	 *
	 * @see FormField::FieldHolder()
	 * @return String
	 */
	public function FieldHolder($properties = array())
	{
		Requirements::javascript('swipestripe-coupon/javascript/CouponModifierField.js');
		return parent::FieldHolder($properties);
	}

	/**
	 * Update value of the field according to any matching {@link Modification}s in the 
	 * {@link Order}. Useful when the source options have changed, if a matching option cannot
	 * be found in a Modification then the first option is set at the value (selected).
	 * 
	 * @param Order $order
	 */
	public function updateValue($order, $data)
	{
		return $this;
	}

	/**
	 * Ensure that the value is the ID of a valid {@link FlatFeeShippingRate} and that the 
	 * FlatFeeShippingRate it represents is valid for the Shipping country being set in the 
	 * {@link Order}.
	 */
	public function validate($validator)
	{

		$valid = true;
		return $valid;
	}

	/**
	 * Set the amount that this field represents.
	 * 
	 * @param DBMoney $amount
	 */
	public function setAmount(DBMoney $amount)
	{
		$this->amount = $amount;
		return $this;
	}

	/**
	 * Return the amount for this tax rate for displaying in the {@link CheckoutForm}
	 * 
	 * @return String
	 */
	public function Description()
	{
		return $this->amount->Nice();
	}

	/**
	 * Shipping field modifies {@link Order} sub total by default.
	 * 
	 * @return Boolean True
	 */
	public function modifiesSubTotal()
	{
		return false;
	}
}

class CouponModifierField_Extension extends Extension
{

	private static $allowed_actions = array(
		'checkcoupon'
	);

	// public function updateOrderForm($form) {
	// 	Requirements::javascript('swipestripe-coupon/javascript/CouponModifierField.js');
	// }

	public function checkcoupon(HTTPRequest $request)
	{
		$code = Convert::raw2sql($request->postVar('CouponCode'));
		
		if (empty($code)) {
			return json_encode(['errorMessage' => 'Please enter a coupon code.']);
		}

		$order = Cart::get_current_order();

		$mod = CouponModification::create();

		if (empty($mod->getValidCouponOrFail($order, $code))) {
			return json_encode(['errorMessage' => 'Coupon is invalid or expired.']);
		}

		return json_encode(['errorMessage' => 'Coupon added.']);
	}
}
