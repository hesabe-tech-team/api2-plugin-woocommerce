# Installation Guide

## Quick Start

1. **Upload Plugin**
   - Upload the `hesabe-woocommerce-payment-gateway` folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins > Add New > Upload Plugin

2. **Activate Plugin**
   - Go to Plugins in WordPress admin
   - Find "Hesabe WooCommerce Payment Gateway"
   - Click "Activate"

3. **Configure Gateway**
   - Navigate to **WooCommerce > Settings > Payments**
   - Find "Hesabe Payment Gateway"
   - Click "Manage" or "Set up"

4. **Basic Configuration**
   - **Enable/Disable**: Check to enable the gateway
   - **Environment Mode**: Select "Staging (Sandbox)" for testing
   - **Use Default Test Credentials**: Check this box (default credentials are pre-filled)
   - **Enable Direct Payment Method**: Optional - enables payment method selection on checkout
   - Click "Save changes"

5. **Test Payment**
   - Add a product to cart
   - Go to checkout
   - Select Hesabe as payment method
   - Complete a test transaction using test cards

## Detailed Configuration

### Staging Mode Setup

1. In gateway settings, select **Environment Mode: Staging (Sandbox)**
2. Check **Use Default Test Credentials**
3. Default credentials are automatically loaded:
   - Merchant Code: `842217`
   - Access Code: `c333729b-d060-4b74-a49d-7686a8353481`
   - Secret Key: `PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg`
   - IV Key: `5NVdrlPVNnjo2Jy9`
4. Save settings

### Live Mode Setup

1. Get your production credentials from https://merchant.hesabe.com
2. In gateway settings, select **Environment Mode: Live (Production)**
3. Enter your production credentials:
   - Live Merchant Code
   - Live Access Code
   - Live Secret Key
   - Live IV Key
4. Save settings
5. Test with a small transaction first

### Direct Payment Configuration

**To enable payment method selection on checkout:**

1. Check **Enable Direct Payment Method**
2. Enable individual payment methods you want to offer:
   - KNET
   - Visa/Mastercard
   - AMEX
   - Apple Pay
   - Apple Pay (KNET)
3. Save settings

**When Direct Payment is disabled:**
- Customers will select payment method on Hesabe payment page
- No additional configuration needed

## Payment Method Images

The plugin references payment method images in `/assets/images/`. If images are missing, you can:

1. Add the following images to `/assets/images/`:
   - `hesabe-new.png` - Main Hesabe logo
   - `knet.png` - KNET logo
   - `mastervisa.png` - Visa/Mastercard logo
   - `amex_new.png` - AMEX logo
   - `apple.png` - Apple Pay logo

2. Images should be PNG format
3. Recommended size: 50-100px width, maintain aspect ratio

## Troubleshooting

### Gateway Not Appearing on Checkout

1. Verify gateway is enabled in WooCommerce > Settings > Payments
2. Check that credentials are properly configured
3. Ensure WooCommerce is active and up to date
4. Clear any caching plugins

### Block Checkout Not Working

1. Ensure WooCommerce Blocks plugin is active
2. Verify compatibility is declared (should be automatic)
3. Check WordPress and WooCommerce versions meet requirements
4. Try classic checkout to isolate the issue

### Payment Processing Errors

1. Verify credentials are correct for selected environment
2. Check API endpoints are accessible
3. Review order notes for detailed error messages
4. Test with default staging credentials first

## Requirements Check

Before installation, ensure:

- ✅ WordPress 5.8+
- ✅ WooCommerce 5.0+
- ✅ PHP 7.4+
- ✅ cURL extension enabled
- ✅ OpenSSL extension enabled
- ✅ WooCommerce Blocks (for block checkout)

## Next Steps

After installation:

1. Test in staging mode with test cards
2. Verify payment flow works correctly
3. Test all enabled payment methods
4. Switch to live mode when ready
5. Monitor first few transactions

## Support

For issues or questions:
- Check Hesabe Developer Documentation: https://developer.hesabe.com/
- Contact Hesabe support team
- Review plugin documentation in README.md

