<?php

namespace Coupon;

use SilverStripe\Control\Controller;
use SilverStripe\Control\PjaxResponseNegotiator;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SwipeStripe\Admin\GridFieldConfig_HasManyRelationEditor;
use SwipeStripe\Admin\ShopAdmin;
use SwipeStripe\Admin\ShopConfig;
use SwipeStripe\Customer\Customer;
use SwipeStripe\Product\Price;

/**
 * Coupon rates that can be set in {@link SiteConfig}. Several flat rates can be set 
 * for any supported shipping country.
 */
class Coupon extends DataObject implements PermissionProvider
{

	private static $table_name = 'Coupon';

	/**
	 * 
	 * @var Array
	 */
	private static $db = array(
		'Title' => 'Varchar',
		'Code' => 'Varchar',
		'Discount' => 'Decimal(18,2)',
		'Type' => 'Enum("Percentage,Flat")',
		'MinimumSpend' => 'Currency',
		'MaxCustomerUses' => 'Int',
		'Expiry' => 'Date'
	);

	/**
	 * Coupon rates are associated with SiteConfigs.
	 * 
	 * @var unknown_type
	 */
	private static $has_one = array(
		'ShopConfig' => ShopConfig::class
	);

	private static $summary_fields = array(
		'Title' => 'Title',
		'Code' => 'Code',
		'SummaryOfDiscount' => 'Discount',
		'Type' => 'Type',
		'MinimumSpend' => 'Minimum Spend',
		'Expiry' => 'Expiry'
	);

	public function providePermissions()
	{
		return array(
			'EDIT_COUPONS' => 'Edit Coupons',
		);
	}

	public function canEdit($member = null)
	{
		return Permission::check('EDIT_COUPONS');
	}

	public function canView($member = null)
	{
		return true;
	}

	public function canDelete($member = null)
	{
		return Permission::check('EDIT_COUPONS');
	}

	public function canCreate($member = null, $context = [])
	{
		return Permission::check('EDIT_COUPONS');
	}

	/**
	 * Field for editing a {@link Coupon}.
	 * 
	 * @return FieldSet
	 */
	public function getCMSFields()
	{

		return new FieldList(
			$rootTab = new TabSet(
				'Root',
				$tabMain = new Tab(
					'CouponRate',
					TextField::create(name: 'Title', title: _t('Coupon.TITLE', 'Title')),
					TextField::create(name: 'Code',title:  _t('Coupon.CODE', 'Code')),
					OptionsetField::create(name: 'Type', title: _t('Coupon.DISCOUNT_TYPE', 'Discount Type'), source: [
						'Percentage' => 'Percentage',
						'Flat' => 'Flat Amount',
					], default: 'Percentage'),
					NumericField::create(name: 'Discount', title: _t('Coupon.DISCOUNT', 'Coupon discount')),
					CurrencyField::create(name: 'MinimumSpend', title: _t('Coupon.MIN_SPEND', 'Minimum Spend'))
						->setRightTitle('Valid for purchases of at least this amount, not including shipping'),
					NumericField::create(name: 'MaxCustomerUses', title: _t('Coupon.MAX_CUST_USES', 'Maximum Uses Per Customer'))
						->setRightTitle('Leave at 0 for unlimited'),
					DateField::create('Expiry')
				)
			)
		);
	}

	/**
	 * Label for using on {@link CouponModifierField}s.
	 * 
	 * @see CouponModifierField
	 * @return String
	 */
	public function Label()
	{
		return $this->Title;
	}

	/**
	 * Summary of the current tax rate
	 * 
	 * @return String
	 */
	public function SummaryOfDiscount()
	{
		return $this->Discount;
	}

	public function Amount($order)
	{

		// TODO: Multi currency

		$shopConfig = ShopConfig::current_shop_config();

		$amount = new Price();
		$amount->setCurrency($shopConfig->BaseCurrency);
		$amount->setSymbol($shopConfig->BaseCurrencySymbol);

		$total = $order->SubTotal()->getAmount();
		$mods = $order->TotalModifications();

		if ($mods && $mods->exists()) foreach ($mods as $mod) {
			if ($mod->ClassName != CouponModification::class) {
				$total += $mod->Amount()->getAmount();
			}
		}

		if ($this->Type === 'Percentage') {
			$amount->setAmount(round(0 - ($total * ($this->Discount / 100)), 2));
		} else {
			$amount->setAmount(round(0 - min($this->Discount, $total)), 2);
		}

		return $amount;
	}

	/**
	 * Display price, can decorate for multiple currency etc.
	 * 
	 * @return Price
	 */
	public function Price($order)
	{

		$amount = $this->Amount($order);
		$this->extend('updatePrice', $amount);
		return $amount;
	}
}

/**
 * So that {@link Coupon}s can be created in {@link SiteConfig}.
 */
class Coupon_Extension extends DataExtension
{

	/**
	 * Attach {@link Coupon}s to {@link SiteConfig}.
	 * 
	 * @see DataObjectDecorator::extraStatics()
	 */
	private static $has_many = array(
		'Coupons' => Coupon::class
	);
}

class Coupon_Admin extends ShopAdmin
{

	private static $tree_class = ShopConfig::class;

	private static $allowed_actions = array(
		'CouponSettings',
		'CouponSettingsForm',
		'saveCouponSettings'
	);

	private static $url_rule = 'SwipeStripe-Admin-ShopConfig/Coupon';
	private static $url_priority = 100;
	private static $menu_title = 'Shop Coupons';

	private static $url_handlers = array(
		'SwipeStripe-Admin-ShopConfig/Coupon/CouponSettingsForm' => 'CouponSettingsForm',
		'SwipeStripe-Admin-ShopConfig/Coupon' => 'CouponSettings'
	);

	protected function init()
	{
		parent::init();
		$this->modelTab = ShopConfig::class;
	}

	public function Breadcrumbs($unlinked = false)
	{

		$request = $this->getRequest();
		$items = parent::Breadcrumbs($unlinked);

		if ($items->count() > 1) $items->remove($items->pop());

		$items->push(new ArrayData(array(
			'Title' => 'Coupon Settings',
			'Link' => $this->Link(Controller::join_links($this->sanitiseClassName($this->modelTab), 'Coupon'))
		)));

		return $items;
	}

	public function SettingsForm($request = null)
	{
		return $this->CouponSettingsForm();
	}

	public function CouponSettings($request)
	{

		if ($request->isAjax()) {
			$controller = $this;
			$responseNegotiator = new PjaxResponseNegotiator(
				array(
					'CurrentForm' => function () use (&$controller) {
						return $controller->CouponSettingsForm()->forTemplate();
					},
					'Content' => function () use (&$controller) {
						return $controller->renderWith('Includes/ShopAdminSettings_Content');
					},
					'Breadcrumbs' => function () use (&$controller) {
						return $controller->renderWith('SilverStripe/Admin/Includes/CMSBreadcrumbs');
					},
					'default' => function () use (&$controller) {
						return $controller->renderWith($controller->getViewer('show'));
					}
				),
				$this->response
			);
			return $responseNegotiator->respond($this->getRequest());
		}

		return $this->renderWith('SwipeStripe/Admin/ShopAdminSettings');
	}

	public function CouponSettingsForm()
	{

		$shopConfig = ShopConfig::get()->First();

		$fields = new FieldList(
			$rootTab = new TabSet(
				'Root',
				$tabMain = new Tab(
					'Coupon',
					GridField::create(
						'Coupons',
						'Coupons',
						$shopConfig->Coupons(),
						GridFieldConfig_HasManyRelationEditor::create()
					)
				)
			)
		);

		$actions = new FieldList();
		$actions->push(FormAction::create('saveCouponSettings', _t('GridFieldDetailForm.Save', 'Save'))
			->setUseButtonTag(true)
			->addExtraClass('btn-outline-primary font-icon-tick action'));

		$form = new Form(
			$this,
			'EditForm',
			$fields,
			$actions
		);

		$form->setTemplate('Includes/ShopAdminSettings_EditForm');
		$form->setAttribute('data-pjax-fragment', 'CurrentForm');
		$form->addExtraClass('cms-content cms-edit-form center ss-tabset');
		if ($form->Fields()->hasTabset()) $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe/Forms/CMSTabSet');
		$form->setFormAction(Controller::join_links($this->Link($this->sanitiseClassName($this->modelTab)), 'Coupon/CouponSettingsForm'));

		$form->loadDataFrom($shopConfig);

		return $form;
	}

	public function saveCouponSettings($data, $form)
	{

		//Hack for LeftAndMain::getRecord()
		self::$tree_class = ShopConfig::class;

		$config = ShopConfig::get()->First();
		$form->saveInto($config);
		$config->write();
		$form->sessionMessage('Saved Coupon Settings', ValidationResult::TYPE_GOOD);

		$controller = $this;
		$responseNegotiator = new PjaxResponseNegotiator(
			array(
				'CurrentForm' => function () use (&$controller) {
					//return $controller->renderWith('Includes/ShopAdminSettings_Content');
					return $controller->CouponSettingsForm()->forTemplate();
				},
				'Content' => function () use (&$controller) {
					//return $controller->renderWith($controller->getTemplatesWithSuffix('_Content'));
				},
				'Breadcrumbs' => function () use (&$controller) {
					return $controller->renderWith('SilverStripe/Admin/Includes/CMSBreadcrumbs');
				},
				'default' => function () use (&$controller) {
					return $controller->renderWith($controller->getViewer('show'));
				}
			),
			$this->response
		);
		return $responseNegotiator->respond($this->getRequest());
	}

	public function getSnippet()
	{

		if (!$member = Security::getCurrentUser()) return false;
		if (!Permission::check('CMS_ACCESS_' . get_class($this), 'any', $member)) return false;

		return $this->customise(array(
			'Title' => 'Coupon Management',
			'Help' => 'Create coupons',
			'Link' => Controller::join_links($this->Link($this->sanitiseClassName(ShopConfig::class)), 'Coupon'),
			'LinkTitle' => 'Edit coupons'
		))->renderWith('Includes/ShopAdmin_Snippet');
	}
}

class Coupon_OrderExtension extends DataExtension
{
	/**
	 * Attach {@link Coupon}s to {@link SiteConfig}.
	 * 
	 * @see DataObjectDecorator::extraStatics()
	 */
	private static $db = array(
		'CouponCode' => 'Varchar'
	);
	// returns the percent discount from the coupon applied to the order
	public function CouponDiscountPercent()
	{
		if ($this->getOwner()->CouponCode) {
			$coupon = Coupon::get()->filter('Code', $this->getOwner()->CouponCode)->first();
			return $coupon ? $coupon->Discount : 0;
		} else {
			return 0;
		}
	}

	/**
	 * The amount deducted by the current coupon, expressed as a positive value
	 */
	public function CouponAmount()
	{
		$mod = $this->getOwner()->Modifications()->filter('ClassName', CouponModification::class)->first();
		if (empty($mod)) {
			return 0;
		}
		return abs($mod->Price);
	}

	// applies coupon percent discount to amount to return the value to be subtracted from the amount
	private function ApplyDiscount($amount)
	{
		return $this->CouponDiscountPercent() * 0.01 * $amount;
	}

	// returns a formatted string version of the discount applied to an item of $amount value
	public function CouponDiscountValue($amount)
	{
		return number_format($this->ApplyDiscount($amount), 2, '.', '');
	}

	// returns the new total value of an item worth $amount after coupn discount is applied
	public function CouponDiscountTotal($amount)
	{
		return number_format($amount - $this->ApplyDiscount($amount), 2, '.', '');
	}
}

class Coupon_CheckoutFormExtension extends Extension
{

	public function getCouponFields()
	{
		$fields = new FieldList();
		$fields->push(
			Coupon_Field::create('CouponCode', _t('Coupon.COUPON_CODE_LABEL', 'Enter your coupon code'))
				->setForm($this->owner)
		);
		return $fields;
	}
}

class Coupon_Field extends TextField
{

	public function FieldHolder($properties = array())
	{
		Requirements::javascript('swipestripe-coupon/javascript/CouponModifierField.js');
		return $this->renderWith('Includes/CouponField');
	}
}

