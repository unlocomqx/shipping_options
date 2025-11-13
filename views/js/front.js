var delivery_id_input = 0;

document.addEventListener('dp-loaded', function() {
  // document.querySelectorAll('#dp_product [name]').forEach(el => el.setAttribute('form', 'js-delivery'));

  const shipping_option = window.getField('shipping_options').init.toString();

  const selectDeliveryOptionOriginal = selectDeliveryOption;
  window.selectDeliveryOption = function(deliveryForm) {
    const delivery_options = document.querySelector('[name^=delivery_option]:checked').value.split(',');
    if(delivery_options.includes(shipping_option)){
      window.dpSaveCustomization(false).then(function(res){
        delivery_id_input = Object.values(res.id_inputs)[0]
        selectDeliveryOptionOriginal(deliveryForm);
      })
    } else {
      selectDeliveryOptionOriginal(deliveryForm);
    }
  }

  window.dp_calc.subscribe(function () {
    window.setTimeout(function() {
      window.selectDeliveryOption($('#js-delivery'));
    }, 100);
  })
});

$(document).ajaxSend(function(event, jqXHR, settings) {
  if (settings.url.includes('selectDeliveryOption')) {
    settings.data += '&id_input=' + delivery_id_input;
  }
});

