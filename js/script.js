function addToCart(productId) {
    console.log('Adding product ID:', productId);
    $.ajax({
        url: 'cart.php',
        type: 'POST',
        data: { action: 'add', product_id: productId },
        dataType: 'json',
        success: function(data) {
            console.log('Response:', data);
            if (data.success) {
                updateCartCount(data.cartCount);
                alert('Product added to cart!');
            } else {
                alert(data.message || 'Error adding to cart');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            alert('Failed to add to cart. Check console for details.');
        }
    });
}

function updateQuantity(productId, quantity) {
    console.log('Updating quantity for product ID:', productId, 'to:', quantity);
    $.ajax({
        url: 'cart.php',
        type: 'POST',
        data: { action: 'update_quantity', product_id: productId, quantity: quantity },
        dataType: 'json',
        success: function(data) {
            console.log('Response:', data);
            if (data.success) {
                updateCartCount(data.cartCount);
                location.reload();
            } else {
                alert(data.message || 'Error updating quantity');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            alert('Failed to update quantity. Check console for details.');
        }
    });
}

function updateCartCount(count) {
    $('.cart-count').text(count);
}

$(document).ready(function() {
    $.ajax({
        url: 'cart.php',
        type: 'GET',
        success: function(data) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const cartTable = doc.querySelector('.cart-table');
            if (cartTable) {
                const count = $(cartTable).find('tr').length - 2; // Exclude header and total rows
                updateCartCount(count);
            } else {
                updateCartCount(0);
            }
        },
        error: function(xhr, status, error) {
            console.error('Cart count fetch error:', status, error);
        }
    });
});