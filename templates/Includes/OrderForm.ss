<% if IncludeFormTag %>
<form $FormAttributes>
<% end_if %>

	<% if Message %>
		<p id="{$FormName}_error" class="message $MessageType">$Message</p>
	<% else %>
		<p id="{$FormName}_error" class="message $MessageType" style="display: none"></p>
	<% end_if %>

	<fieldset>

		<% if PersonalDetailsFields %>
		<section class="personal-details">
			<% loop PersonalDetailsFields %>
				$FieldHolder
			<% end_loop %>
		</section>
		
		<hr />
		<% end_if %>

		<!-- Address fields if the addresses module is installed -->
		<section class="address">
			<div id="address-shipping">
				<% loop ShippingAddressFields %>
					$FieldHolder
				<% end_loop %>
			</div>
		</section>

		<hr />
	
		<section class="address">
			<div id="address-billing">
				<% loop BillingAddressFields %>
					$FieldHolder
				<% end_loop %>
			</div>
		</section>
		
		<hr />
		<!-- End of address fields -->
		
		<!-- Add coupon fields to the OrderForm template -->
		<section class="coupon">
			<h3><% _t('Coupon.COUPON', 'Coupon') %></h3>
			<% loop CouponFields %>
				$FieldHolder
			<% end_loop %>
		</section>
		
		<hr />
		<!-- End of coupon fields -->
		
		<section class="order-details">
			<h3><% _t('CheckoutForm.YOUR_ORDER', 'Your Order') %></h3>

			<div id="cart-loading-js" class="cart-loading">
				<div>
					<h4>Loading...</h4>
				</div>
			</div>
			
			<% include OrderFormCart %>
		</section>
	 

		<section class="notes">
			<% loop NotesFields %>
				$FieldHolder
			<% end_loop %>
		</section>
		
		<hr />
	 
		<section class="payment-details">
			<% loop PaymentFields %>
				$FieldHolder
			<% end_loop %>
		</section>

		<div class="clear" />
	</fieldset>

	<% if Cart.Items %>
		<% if Actions %>
		<div class="Actions">
			<div class="loading">
				<img src="resources/swipestripe/images/loading.gif" />
			</div>
			<% loop Actions %>
				$Field
			<% end_loop %>
		</div>
		<% end_if %>
	<% end_if %>
	
<% if IncludeFormTag %>
</form>
<% end_if %>