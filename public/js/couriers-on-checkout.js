// JavaScript for Public Checkout Area
(function ($) {

    $(document).ready(function () {

        var requestRatesBtn = $('#request_courier_rates');

        requestRatesBtn.click(function (e) {

            e.preventDefault();
            $('#shipping-notice').remove();

            // initialize variables
            let firstName = lastName = email = phone = selectedCountry = selectedState = city = streetAddress = '';
            //08036922

            let shippingStateRequired = billingStateRequired = 0;

            let useShippingAddress = $('input#ship-to-different-address-checkbox');

            // order comments
            orderComments = $('textarea#order_comments').val();

            // use shipping variables
            if (useShippingAddress.is(':checked')) {

                firstName = $('input#shipping_first_name').val();
                lastName = $('input#shipping_last_name').val();
                city = $('input#shipping_city').val();
                streetAddress = $('input#shipping_address_1').val();

                if ($('input#shipping_email').val() == undefined) {
                    email = $('input#billing_email').val();
                } else {
                    email = $('input#shipping_email').val();
                }

                if ($('input#shipping_phone').val() == undefined) {
                    phone = $('input#billing_phone').val();
                } else {
                    phone = $('input#shipping_phone').val();
                }

                if ($('select#shipping_city').length) {
                    city = $('select#shipping_city option:selected').text();
                } else {
                    city = $('input#shipping_city').val();
                }

                if ($('select#shipping_country').length) {
                    selectedCountry = $('select#shipping_country option:selected').text();
                } else {
                    selectedCountry = $('input#shipping_country').val();
                    selectedCountry = getCountryCode(selectedCountry);
                }

                billingStateRequired = $('label[for="billing_state"]').find('abbr.required').length;

                if ($('select#shipping_state').length) {
                    selectedState = $('select#shipping_state option:selected').text();
                } else {
                    selectedState = $('input#shipping_state').val();
                }

            } else {

                // use billing variables
                firstName = $('input#billing_first_name').val();
                lastName = $('input#billing_last_name').val();
                email = $('input#billing_email').val();
                phone = $('input#billing_phone').val();
                streetAddress = $('input#billing_address_1').val();

                if ($('select#billing_city').length) {
                    city = $('select#billing_city option:selected').text();
                } else {
                    city = $('input#billing_city').val();
                }

                if ($('select#billing_country').length) {
                    selectedCountry = $('select#billing_country option:selected').text();
                } else {
                    selectedCountry = $('input#billing_country').val();
                    selectedCountry = getCountryCode(selectedCountry);
                }

                shippingStateRequired = $('label[for="shipping_state"]').find('abbr.required').length;

                if ($('select#billing_state').length) {
                    selectedState = $('select#billing_state option:selected').text();
                } else {
                    selectedState = $('input#billing_state').val();
                }
            }

            // check requirements are met
            if (
                (((!billingStateRequired || !shippingStateRequired) && selectedState.length >= 0)
                    || (billingStateRequired || shippingStateRequired) && selectedState.length > 0)
                &&
                firstName != '' && lastName != '' && email != '' && phone != '' && streetAddress != '' && city != '' && selectedCountry != '') {
                // hide notice
                $('#shipping-notice').remove();

                // Assemble payload
                let addressPayload = {
                    name: firstName + ' ' + lastName,
                    email,
                    phone,
                    address: streetAddress + ', ' + city + ', ' + selectedState + ', ' + selectedCountry,
                    comments: orderComments,
                }

                let sbSlogan = $('.sb-slogan-container');
                sbSlogan.show();

                // disable request btn
                $(this).prop('disabled', true);
                // $(this).addClass('load');

                // Request shipping rates
                fetch_shipping_rates(addressPayload);
            } else {
                // Display notice
                let errorBox = [];
                let containerObject = { firstName, lastName, email, phone, streetAddress, city, selectedCountry }

                if ((billingStateRequired || shippingStateRequired)) {
                    containerObject['selectedState'] = '';
                }

                for (const key in containerObject) {
                    if (containerObject[key] == '') {
                        let kName = '';

                        if (key.includes('selectedState') && (billingStateRequired || shippingStateRequired)) {
                            kName = 'selected state or county';
                        } else {
                            kName = key.split(/(?=[A-Z])/).join(' ').toLowerCase();
                        }

                        errorBox.push(`${kName}`);
                    }
                }

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                    style: 'font-size:16px',
                }).text(`Ensure that you have filled your ${errorBox.join(', ')}`).appendTo('#order_review_heading').show();
            }

        });


        function fetch_shipping_rates(payload) {
            // submit the data
            let ajaxUrl = ajax_public.ajaxurl;

            // set ajax payload
            let data = {
                nonce: ajax_public.nonce,
                action: 'request_shipping_rates',
                data: payload
            };

            // initialize courier listing html container
            let list = $('#courier-list');

            list.empty();

            list.append(`
                <div class="container-delivery-card-header">
                    <p id="sb-status-text">Fetching delivery prices...</p>
                </div>
            `);

            let newCourierList = $('<div class="container-delivery-card-list shipbubble-loading"></div');

            list.append(newCourierList);

            let loaders = $(`<div class="shipbubble-loading">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>`);

            $(loaders).insertAfter(newCourierList);

            loaders.show();

            $.post(
                ajaxUrl,
                data,
            ).done(function (data) {

                let response = JSON.parse(data);

                if (response.hasOwnProperty('status')) {
                    if (response['status'] == 'success') {
                        let output = response['data'];

                        // dynamically add each courier
                        $('#sb-status-text').html('Select a delivery option');

                        // log time of data fetch
                        var json_fetch_date = new Date().toLocaleString();
                        $('input[name="shipbubble_rate_datetime"]').val(json_fetch_date);

                        loaders.hide();

                        newCourierList.removeClass('shipbubble-loading');

                        $.each(output.couriers, function (i, value) {
                            // set total charge
                            let total = parseFloat(value.rate_card_amount) + parseFloat(output.extra_charges);

                            newCourierList.append(`
                                <div class="container-delivery-card-list-item">
                                    <div class="container-delivery-card-list-item-top">
                                        <img
                                            src="${value.courier_image}" />
                                        <div class="message">
                                            <p class="title">${value.courier_name}</p>
                                            <span>
                                                <span>${value.delivery_eta}</span>
                                            </span>
                                        </div>
                                    </div>
            
                                    <div class='radio-item special-radio'>
                                        <input type='radio' id="${value.courier_id}_${i}" name="delivery_option" 
                                        data-request_token="${output.request_token}" data-courier_name="${value.courier_name}" data-cost="${total}" data-service_code="${value.service_code}" data-courier_id="${value.courier_id}"
                                        />
                                        <label for='${value.courier_id}_${i}'>
                                            <p>
                                                ${output.currency_symbol} ${total.toLocaleString()}
                                            </p>
                                            <span class='address-span'></span>
            
                                        </label>
                                    </div>
                                </div>
                            `);

                        });

                        const courier_radio_btn = $('input[name="delivery_option"]');

                        courier_radio_btn.change(function () {
                            //first remove class from all
                            courier_radio_btn.parent().parent().removeClass('active');

                            if ($(this).is(':checked')) {
                                $(this).parent().parent().addClass('active')
                            }
                        });


                    } else {
                        let sbSlogan = $('.sb-slogan-container');
                        sbSlogan.hide();

                        list.empty();

                        let responseMessage = '';

                        if (response.hasOwnProperty('errors')) {
                            responseMessage = response['errors'][0];
                        } else if (response.hasOwnProperty('message')) {
                            responseMessage = response['message'];
                        } else if (response.hasOwnProperty('data')) {
                            responseMessage = response['data'];
                        } else {
                            responseMessage = 'unable to fetch rates, contact admin';
                        }

                        $('<div>', {
                            id: 'shipping-notice',
                            class: 'woocommerce-info',
                            style: 'font-size:16px',
                        }).text(`${responseMessage}`).appendTo('#order_review_heading').show();

						Swal.fire({
							title: '',
							text: responseMessage,
							showConfirmButton: false,
							showCloseButton: true,
							width: 400,
							customClass: {
								closeButton: "shipbubble-close-button"
							}
						});

                    }
                }

                // var requestRatesBtn = $('#request_courier_rates');
                // requestRatesBtn.prop('disabled', false);
                // requestRatesBtn.removeClass('load');

            }).fail(function () {
                let sbSlogan = $('.sb-slogan-container');
                sbSlogan.hide();

                list.empty();

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                    style: 'font-size:16px',
                }).text(`unable to display couriers list, please try again later`).appendTo('#order_review_heading').show();

            });

            var requestRatesBtn = $('#request_courier_rates');
            requestRatesBtn.prop('disabled', false);

        }

        const countryCodes = {
            "AF": {
                "name": "Afghanistan",
                "dial_code": "+93",
                "code": "AF"
            },
            "AX": {
                "name": "Aland Islands",
                "dial_code": "+358",
                "code": "AX"
            },
            "AL": {
                "name": "Albania",
                "dial_code": "+355",
                "code": "AL"
            },
            "DZ": {
                "name": "Algeria",
                "dial_code": "+213",
                "code": "DZ"
            },
            "AS": {
                "name": "AmericanSamoa",
                "dial_code": "+1684",
                "code": "AS"
            },
            "AD": {
                "name": "Andorra",
                "dial_code": "+376",
                "code": "AD"
            },
            "AO": {
                "name": "Angola",
                "dial_code": "+244",
                "code": "AO"
            },
            "AI": {
                "name": "Anguilla",
                "dial_code": "+1264",
                "code": "AI"
            },
            "AQ": {
                "name": "Antarctica",
                "dial_code": "+672",
                "code": "AQ"
            },
            "AG": {
                "name": "Antigua and Barbuda",
                "dial_code": "+1268",
                "code": "AG"
            },
            "AR": {
                "name": "Argentina",
                "dial_code": "+54",
                "code": "AR"
            },
            "AM": {
                "name": "Armenia",
                "dial_code": "+374",
                "code": "AM"
            },
            "AW": {
                "name": "Aruba",
                "dial_code": "+297",
                "code": "AW"
            },
            "AU": {
                "name": "Australia",
                "dial_code": "+61",
                "code": "AU"
            },
            "AT": {
                "name": "Austria",
                "dial_code": "+43",
                "code": "AT"
            },
            "AZ": {
                "name": "Azerbaijan",
                "dial_code": "+994",
                "code": "AZ"
            },
            "BS": {
                "name": "Bahamas",
                "dial_code": "+1242",
                "code": "BS"
            },
            "BH": {
                "name": "Bahrain",
                "dial_code": "+973",
                "code": "BH"
            },
            "BD": {
                "name": "Bangladesh",
                "dial_code": "+880",
                "code": "BD"
            },
            "BB": {
                "name": "Barbados",
                "dial_code": "+1246",
                "code": "BB"
            },
            "BY": {
                "name": "Belarus",
                "dial_code": "+375",
                "code": "BY"
            },
            "BE": {
                "name": "Belgium",
                "dial_code": "+32",
                "code": "BE"
            },
            "BZ": {
                "name": "Belize",
                "dial_code": "+501",
                "code": "BZ"
            },
            "BJ": {
                "name": "Benin",
                "dial_code": "+229",
                "code": "BJ"
            },
            "BM": {
                "name": "Bermuda",
                "dial_code": "+1441",
                "code": "BM"
            },
            "BT": {
                "name": "Bhutan",
                "dial_code": "+975",
                "code": "BT"
            },
            "BO": {
                "name": "Bolivia, Plurinational State of",
                "dial_code": "+591",
                "code": "BO"
            },
            "BA": {
                "name": "Bosnia and Herzegovina",
                "dial_code": "+387",
                "code": "BA"
            },
            "BW": {
                "name": "Botswana",
                "dial_code": "+267",
                "code": "BW"
            },
            "BR": {
                "name": "Brazil",
                "dial_code": "+55",
                "code": "BR"
            },
            "IO": {
                "name": "British Indian Ocean Territory",
                "dial_code": "+246",
                "code": "IO"
            },
            "BN": {
                "name": "Brunei Darussalam",
                "dial_code": "+673",
                "code": "BN"
            },
            "BG": {
                "name": "Bulgaria",
                "dial_code": "+359",
                "code": "BG"
            },
            "BF": {
                "name": "Burkina Faso",
                "dial_code": "+226",
                "code": "BF"
            },
            "BI": {
                "name": "Burundi",
                "dial_code": "+257",
                "code": "BI"
            },
            "KH": {
                "name": "Cambodia",
                "dial_code": "+855",
                "code": "KH"
            },
            "CM": {
                "name": "Cameroon",
                "dial_code": "+237",
                "code": "CM"
            },
            "CA": {
                "name": "Canada",
                "dial_code": "+1",
                "code": "CA"
            },
            "CV": {
                "name": "Cape Verde",
                "dial_code": "+238",
                "code": "CV"
            },
            "KY": {
                "name": "Cayman Islands",
                "dial_code": "+ 345",
                "code": "KY"
            },
            "CF": {
                "name": "Central African Republic",
                "dial_code": "+236",
                "code": "CF"
            },
            "TD": {
                "name": "Chad",
                "dial_code": "+235",
                "code": "TD"
            },
            "CL": {
                "name": "Chile",
                "dial_code": "+56",
                "code": "CL"
            },
            "CN": {
                "name": "China",
                "dial_code": "+86",
                "code": "CN"
            },
            "CX": {
                "name": "Christmas Island",
                "dial_code": "+61",
                "code": "CX"
            },
            "CC": {
                "name": "Cocos (Keeling) Islands",
                "dial_code": "+61",
                "code": "CC"
            },
            "CO": {
                "name": "Colombia",
                "dial_code": "+57",
                "code": "CO"
            },
            "KM": {
                "name": "Comoros",
                "dial_code": "+269",
                "code": "KM"
            },
            "CG": {
                "name": "Congo",
                "dial_code": "+242",
                "code": "CG"
            },
            "CD": {
                "name": "Congo, The Democratic Republic of the Congo",
                "dial_code": "+243",
                "code": "CD"
            },
            "CK": {
                "name": "Cook Islands",
                "dial_code": "+682",
                "code": "CK"
            },
            "CR": {
                "name": "Costa Rica",
                "dial_code": "+506",
                "code": "CR"
            },
            "CI": {
                "name": "Cote d'Ivoire",
                "dial_code": "+225",
                "code": "CI"
            },
            "HR": {
                "name": "Croatia",
                "dial_code": "+385",
                "code": "HR"
            },
            "CU": {
                "name": "Cuba",
                "dial_code": "+53",
                "code": "CU"
            },
            "CY": {
                "name": "Cyprus",
                "dial_code": "+357",
                "code": "CY"
            },
            "CZ": {
                "name": "Czech Republic",
                "dial_code": "+420",
                "code": "CZ"
            },
            "DK": {
                "name": "Denmark",
                "dial_code": "+45",
                "code": "DK"
            },
            "DJ": {
                "name": "Djibouti",
                "dial_code": "+253",
                "code": "DJ"
            },
            "DM": {
                "name": "Dominica",
                "dial_code": "+1767",
                "code": "DM"
            },
            "DO": {
                "name": "Dominican Republic",
                "dial_code": "+1849",
                "code": "DO"
            },
            "EC": {
                "name": "Ecuador",
                "dial_code": "+593",
                "code": "EC"
            },
            "EG": {
                "name": "Egypt",
                "dial_code": "+20",
                "code": "EG"
            },
            "SV": {
                "name": "El Salvador",
                "dial_code": "+503",
                "code": "SV"
            },
            "GQ": {
                "name": "Equatorial Guinea",
                "dial_code": "+240",
                "code": "GQ"
            },
            "ER": {
                "name": "Eritrea",
                "dial_code": "+291",
                "code": "ER"
            },
            "EE": {
                "name": "Estonia",
                "dial_code": "+372",
                "code": "EE"
            },
            "ET": {
                "name": "Ethiopia",
                "dial_code": "+251",
                "code": "ET"
            },
            "FK": {
                "name": "Falkland Islands (Malvinas)",
                "dial_code": "+500",
                "code": "FK"
            },
            "FO": {
                "name": "Faroe Islands",
                "dial_code": "+298",
                "code": "FO"
            },
            "FJ": {
                "name": "Fiji",
                "dial_code": "+679",
                "code": "FJ"
            },
            "FI": {
                "name": "Finland",
                "dial_code": "+358",
                "code": "FI"
            },
            "FR": {
                "name": "France",
                "dial_code": "+33",
                "code": "FR"
            },
            "GF": {
                "name": "French Guiana",
                "dial_code": "+594",
                "code": "GF"
            },
            "PF": {
                "name": "French Polynesia",
                "dial_code": "+689",
                "code": "PF"
            },
            "GA": {
                "name": "Gabon",
                "dial_code": "+241",
                "code": "GA"
            },
            "GM": {
                "name": "Gambia",
                "dial_code": "+220",
                "code": "GM"
            },
            "GE": {
                "name": "Georgia",
                "dial_code": "+995",
                "code": "GE"
            },
            "DE": {
                "name": "Germany",
                "dial_code": "+49",
                "code": "DE"
            },
            "GH": {
                "name": "Ghana",
                "dial_code": "+233",
                "code": "GH"
            },
            "GI": {
                "name": "Gibraltar",
                "dial_code": "+350",
                "code": "GI"
            },
            "GR": {
                "name": "Greece",
                "dial_code": "+30",
                "code": "GR"
            },
            "GL": {
                "name": "Greenland",
                "dial_code": "+299",
                "code": "GL"
            },
            "GD": {
                "name": "Grenada",
                "dial_code": "+1473",
                "code": "GD"
            },
            "GP": {
                "name": "Guadeloupe",
                "dial_code": "+590",
                "code": "GP"
            },
            "GU": {
                "name": "Guam",
                "dial_code": "+1671",
                "code": "GU"
            },
            "GT": {
                "name": "Guatemala",
                "dial_code": "+502",
                "code": "GT"
            },
            "GG": {
                "name": "Guernsey",
                "dial_code": "+44",
                "code": "GG"
            },
            "GN": {
                "name": "Guinea",
                "dial_code": "+224",
                "code": "GN"
            },
            "GW": {
                "name": "Guinea-Bissau",
                "dial_code": "+245",
                "code": "GW"
            },
            "GY": {
                "name": "Guyana",
                "dial_code": "+595",
                "code": "GY"
            },
            "HT": {
                "name": "Haiti",
                "dial_code": "+509",
                "code": "HT"
            },
            "VA": {
                "name": "Holy See (Vatican City State)",
                "dial_code": "+379",
                "code": "VA"
            },
            "HN": {
                "name": "Honduras",
                "dial_code": "+504",
                "code": "HN"
            },
            "HK": {
                "name": "Hong Kong",
                "dial_code": "+852",
                "code": "HK"
            },
            "HU": {
                "name": "Hungary",
                "dial_code": "+36",
                "code": "HU"
            },
            "IS": {
                "name": "Iceland",
                "dial_code": "+354",
                "code": "IS"
            },
            "IN": {
                "name": "India",
                "dial_code": "+91",
                "code": "IN"
            },
            "ID": {
                "name": "Indonesia",
                "dial_code": "+62",
                "code": "ID"
            },
            "IR": {
                "name": "Iran, Islamic Republic of Persian Gulf",
                "dial_code": "+98",
                "code": "IR"
            },
            "IQ": {
                "name": "Iraq",
                "dial_code": "+964",
                "code": "IQ"
            },
            "IE": {
                "name": "Ireland",
                "dial_code": "+353",
                "code": "IE"
            },
            "IM": {
                "name": "Isle of Man",
                "dial_code": "+44",
                "code": "IM"
            },
            "IL": {
                "name": "Israel",
                "dial_code": "+972",
                "code": "IL"
            },
            "IT": {
                "name": "Italy",
                "dial_code": "+39",
                "code": "IT"
            },
            "JM": {
                "name": "Jamaica",
                "dial_code": "+1876",
                "code": "JM"
            },
            "JP": {
                "name": "Japan",
                "dial_code": "+81",
                "code": "JP"
            },
            "JE": {
                "name": "Jersey",
                "dial_code": "+44",
                "code": "JE"
            },
            "JO": {
                "name": "Jordan",
                "dial_code": "+962",
                "code": "JO"
            },
            "KZ": {
                "name": "Kazakhstan",
                "dial_code": "+77",
                "code": "KZ"
            },
            "KE": {
                "name": "Kenya",
                "dial_code": "+254",
                "code": "KE"
            },
            "KI": {
                "name": "Kiribati",
                "dial_code": "+686",
                "code": "KI"
            },
            "KP": {
                "name": "Korea, Democratic People's Republic of Korea",
                "dial_code": "+850",
                "code": "KP"
            },
            "KR": {
                "name": "Korea, Republic of South Korea",
                "dial_code": "+82",
                "code": "KR"
            },
            "KW": {
                "name": "Kuwait",
                "dial_code": "+965",
                "code": "KW"
            },
            "KG": {
                "name": "Kyrgyzstan",
                "dial_code": "+996",
                "code": "KG"
            },
            "LA": {
                "name": "Laos",
                "dial_code": "+856",
                "code": "LA"
            },
            "LV": {
                "name": "Latvia",
                "dial_code": "+371",
                "code": "LV"
            },
            "LB": {
                "name": "Lebanon",
                "dial_code": "+961",
                "code": "LB"
            },
            "LS": {
                "name": "Lesotho",
                "dial_code": "+266",
                "code": "LS"
            },
            "LR": {
                "name": "Liberia",
                "dial_code": "+231",
                "code": "LR"
            },
            "LY": {
                "name": "Libyan Arab Jamahiriya",
                "dial_code": "+218",
                "code": "LY"
            },
            "LI": {
                "name": "Liechtenstein",
                "dial_code": "+423",
                "code": "LI"
            },
            "LT": {
                "name": "Lithuania",
                "dial_code": "+370",
                "code": "LT"
            },
            "LU": {
                "name": "Luxembourg",
                "dial_code": "+352",
                "code": "LU"
            },
            "MO": {
                "name": "Macao",
                "dial_code": "+853",
                "code": "MO"
            },
            "MK": {
                "name": "Macedonia",
                "dial_code": "+389",
                "code": "MK"
            },
            "MG": {
                "name": "Madagascar",
                "dial_code": "+261",
                "code": "MG"
            },
            "MW": {
                "name": "Malawi",
                "dial_code": "+265",
                "code": "MW"
            },
            "MY": {
                "name": "Malaysia",
                "dial_code": "+60",
                "code": "MY"
            },
            "MV": {
                "name": "Maldives",
                "dial_code": "+960",
                "code": "MV"
            },
            "ML": {
                "name": "Mali",
                "dial_code": "+223",
                "code": "ML"
            },
            "MT": {
                "name": "Malta",
                "dial_code": "+356",
                "code": "MT"
            },
            "MH": {
                "name": "Marshall Islands",
                "dial_code": "+692",
                "code": "MH"
            },
            "MQ": {
                "name": "Martinique",
                "dial_code": "+596",
                "code": "MQ"
            },
            "MR": {
                "name": "Mauritania",
                "dial_code": "+222",
                "code": "MR"
            },
            "MU": {
                "name": "Mauritius",
                "dial_code": "+230",
                "code": "MU"
            },
            "YT": {
                "name": "Mayotte",
                "dial_code": "+262",
                "code": "YT"
            },
            "MX": {
                "name": "Mexico",
                "dial_code": "+52",
                "code": "MX"
            },
            "FM": {
                "name": "Micronesia, Federated States of Micronesia",
                "dial_code": "+691",
                "code": "FM"
            },
            "MD": {
                "name": "Moldova",
                "dial_code": "+373",
                "code": "MD"
            },
            "MC": {
                "name": "Monaco",
                "dial_code": "+377",
                "code": "MC"
            },
            "MN": {
                "name": "Mongolia",
                "dial_code": "+976",
                "code": "MN"
            },
            "ME": {
                "name": "Montenegro",
                "dial_code": "+382",
                "code": "ME"
            },
            "MS": {
                "name": "Montserrat",
                "dial_code": "+1664",
                "code": "MS"
            },
            "MA": {
                "name": "Morocco",
                "dial_code": "+212",
                "code": "MA"
            },
            "MZ": {
                "name": "Mozambique",
                "dial_code": "+258",
                "code": "MZ"
            },
            "MM": {
                "name": "Myanmar",
                "dial_code": "+95",
                "code": "MM"
            },
            "NA": {
                "name": "Namibia",
                "dial_code": "+264",
                "code": "NA"
            },
            "NR": {
                "name": "Nauru",
                "dial_code": "+674",
                "code": "NR"
            },
            "NP": {
                "name": "Nepal",
                "dial_code": "+977",
                "code": "NP"
            },
            "NL": {
                "name": "Netherlands",
                "dial_code": "+31",
                "code": "NL"
            },
            "AN": {
                "name": "Netherlands Antilles",
                "dial_code": "+599",
                "code": "AN"
            },
            "NC": {
                "name": "New Caledonia",
                "dial_code": "+687",
                "code": "NC"
            },
            "NZ": {
                "name": "New Zealand",
                "dial_code": "+64",
                "code": "NZ"
            },
            "NI": {
                "name": "Nicaragua",
                "dial_code": "+505",
                "code": "NI"
            },
            "NE": {
                "name": "Niger",
                "dial_code": "+227",
                "code": "NE"
            },
            "NG": {
                "name": "Nigeria",
                "dial_code": "+234",
                "code": "NG"
            },
            "NU": {
                "name": "Niue",
                "dial_code": "+683",
                "code": "NU"
            },
            "NF": {
                "name": "Norfolk Island",
                "dial_code": "+672",
                "code": "NF"
            },
            "MP": {
                "name": "Northern Mariana Islands",
                "dial_code": "+1670",
                "code": "MP"
            },
            "NO": {
                "name": "Norway",
                "dial_code": "+47",
                "code": "NO"
            },
            "OM": {
                "name": "Oman",
                "dial_code": "+968",
                "code": "OM"
            },
            "PK": {
                "name": "Pakistan",
                "dial_code": "+92",
                "code": "PK"
            },
            "PW": {
                "name": "Palau",
                "dial_code": "+680",
                "code": "PW"
            },
            "PS": {
                "name": "Palestinian Territory, Occupied",
                "dial_code": "+970",
                "code": "PS"
            },
            "PA": {
                "name": "Panama",
                "dial_code": "+507",
                "code": "PA"
            },
            "PG": {
                "name": "Papua New Guinea",
                "dial_code": "+675",
                "code": "PG"
            },
            "PY": {
                "name": "Paraguay",
                "dial_code": "+595",
                "code": "PY"
            },
            "PE": {
                "name": "Peru",
                "dial_code": "+51",
                "code": "PE"
            },
            "PH": {
                "name": "Philippines",
                "dial_code": "+63",
                "code": "PH"
            },
            "PN": {
                "name": "Pitcairn",
                "dial_code": "+872",
                "code": "PN"
            },
            "PL": {
                "name": "Poland",
                "dial_code": "+48",
                "code": "PL"
            },
            "PT": {
                "name": "Portugal",
                "dial_code": "+351",
                "code": "PT"
            },
            "PR": {
                "name": "Puerto Rico",
                "dial_code": "+1939",
                "code": "PR"
            },
            "QA": {
                "name": "Qatar",
                "dial_code": "+974",
                "code": "QA"
            },
            "RO": {
                "name": "Romania",
                "dial_code": "+40",
                "code": "RO"
            },
            "RU": {
                "name": "Russia",
                "dial_code": "+7",
                "code": "RU"
            },
            "RW": {
                "name": "Rwanda",
                "dial_code": "+250",
                "code": "RW"
            },
            "RE": {
                "name": "Reunion",
                "dial_code": "+262",
                "code": "RE"
            },
            "BL": {
                "name": "Saint Barthelemy",
                "dial_code": "+590",
                "code": "BL"
            },
            "SH": {
                "name": "Saint Helena, Ascension and Tristan Da Cunha",
                "dial_code": "+290",
                "code": "SH"
            },
            "KN": {
                "name": "Saint Kitts and Nevis",
                "dial_code": "+1869",
                "code": "KN"
            },
            "LC": {
                "name": "Saint Lucia",
                "dial_code": "+1758",
                "code": "LC"
            },
            "MF": {
                "name": "Saint Martin",
                "dial_code": "+590",
                "code": "MF"
            },
            "PM": {
                "name": "Saint Pierre and Miquelon",
                "dial_code": "+508",
                "code": "PM"
            },
            "VC": {
                "name": "Saint Vincent and the Grenadines",
                "dial_code": "+1784",
                "code": "VC"
            },
            "WS": {
                "name": "Samoa",
                "dial_code": "+685",
                "code": "WS"
            },
            "SM": {
                "name": "San Marino",
                "dial_code": "+378",
                "code": "SM"
            },
            "ST": {
                "name": "Sao Tome and Principe",
                "dial_code": "+239",
                "code": "ST"
            },
            "SA": {
                "name": "Saudi Arabia",
                "dial_code": "+966",
                "code": "SA"
            },
            "SN": {
                "name": "Senegal",
                "dial_code": "+221",
                "code": "SN"
            },
            "RS": {
                "name": "Serbia",
                "dial_code": "+381",
                "code": "RS"
            },
            "SC": {
                "name": "Seychelles",
                "dial_code": "+248",
                "code": "SC"
            },
            "SL": {
                "name": "Sierra Leone",
                "dial_code": "+232",
                "code": "SL"
            },
            "SG": {
                "name": "Singapore",
                "dial_code": "+65",
                "code": "SG"
            },
            "SK": {
                "name": "Slovakia",
                "dial_code": "+421",
                "code": "SK"
            },
            "SI": {
                "name": "Slovenia",
                "dial_code": "+386",
                "code": "SI"
            },
            "SB": {
                "name": "Solomon Islands",
                "dial_code": "+677",
                "code": "SB"
            },
            "SO": {
                "name": "Somalia",
                "dial_code": "+252",
                "code": "SO"
            },
            "ZA": {
                "name": "South Africa",
                "dial_code": "+27",
                "code": "ZA"
            },
            "SS": {
                "name": "South Sudan",
                "dial_code": "+211",
                "code": "SS"
            },
            "GS": {
                "name": "South Georgia and the South Sandwich Islands",
                "dial_code": "+500",
                "code": "GS"
            },
            "ES": {
                "name": "Spain",
                "dial_code": "+34",
                "code": "ES"
            },
            "LK": {
                "name": "Sri Lanka",
                "dial_code": "+94",
                "code": "LK"
            },
            "SD": {
                "name": "Sudan",
                "dial_code": "+249",
                "code": "SD"
            },
            "SR": {
                "name": "Suriname",
                "dial_code": "+597",
                "code": "SR"
            },
            "SJ": {
                "name": "Svalbard and Jan Mayen",
                "dial_code": "+47",
                "code": "SJ"
            },
            "SZ": {
                "name": "Swaziland",
                "dial_code": "+268",
                "code": "SZ"
            },
            "SE": {
                "name": "Sweden",
                "dial_code": "+46",
                "code": "SE"
            },
            "CH": {
                "name": "Switzerland",
                "dial_code": "+41",
                "code": "CH"
            },
            "SY": {
                "name": "Syrian Arab Republic",
                "dial_code": "+963",
                "code": "SY"
            },
            "TW": {
                "name": "Taiwan",
                "dial_code": "+886",
                "code": "TW"
            },
            "TJ": {
                "name": "Tajikistan",
                "dial_code": "+992",
                "code": "TJ"
            },
            "TZ": {
                "name": "Tanzania, United Republic of Tanzania",
                "dial_code": "+255",
                "code": "TZ"
            },
            "TH": {
                "name": "Thailand",
                "dial_code": "+66",
                "code": "TH"
            },
            "TL": {
                "name": "Timor-Leste",
                "dial_code": "+670",
                "code": "TL"
            },
            "TG": {
                "name": "Togo",
                "dial_code": "+228",
                "code": "TG"
            },
            "TK": {
                "name": "Tokelau",
                "dial_code": "+690",
                "code": "TK"
            },
            "TO": {
                "name": "Tonga",
                "dial_code": "+676",
                "code": "TO"
            },
            "TT": {
                "name": "Trinidad and Tobago",
                "dial_code": "+1868",
                "code": "TT"
            },
            "TN": {
                "name": "Tunisia",
                "dial_code": "+216",
                "code": "TN"
            },
            "TR": {
                "name": "Turkey",
                "dial_code": "+90",
                "code": "TR"
            },
            "TM": {
                "name": "Turkmenistan",
                "dial_code": "+993",
                "code": "TM"
            },
            "TC": {
                "name": "Turks and Caicos Islands",
                "dial_code": "+1649",
                "code": "TC"
            },
            "TV": {
                "name": "Tuvalu",
                "dial_code": "+688",
                "code": "TV"
            },
            "UG": {
                "name": "Uganda",
                "dial_code": "+256",
                "code": "UG"
            },
            "UA": {
                "name": "Ukraine",
                "dial_code": "+380",
                "code": "UA"
            },
            "AE": {
                "name": "United Arab Emirates",
                "dial_code": "+971",
                "code": "AE"
            },
            "GB": {
                "name": "United Kingdom",
                "dial_code": "+44",
                "code": "GB"
            },
            "US": {
                "name": "United States",
                "dial_code": "+1",
                "code": "US"
            },
            "UY": {
                "name": "Uruguay",
                "dial_code": "+598",
                "code": "UY"
            },
            "UZ": {
                "name": "Uzbekistan",
                "dial_code": "+998",
                "code": "UZ"
            },
            "VU": {
                "name": "Vanuatu",
                "dial_code": "+678",
                "code": "VU"
            },
            "VE": {
                "name": "Venezuela, Bolivarian Republic of Venezuela",
                "dial_code": "+58",
                "code": "VE"
            },
            "VN": {
                "name": "Vietnam",
                "dial_code": "+84",
                "code": "VN"
            },
            "VG": {
                "name": "Virgin Islands, British",
                "dial_code": "+1284",
                "code": "VG"
            },
            "VI": {
                "name": "Virgin Islands, U.S.",
                "dial_code": "+1340",
                "code": "VI"
            },
            "WF": {
                "name": "Wallis and Futuna",
                "dial_code": "+681",
                "code": "WF"
            },
            "YE": {
                "name": "Yemen",
                "dial_code": "+967",
                "code": "YE"
            },
            "ZM": {
                "name": "Zambia",
                "dial_code": "+260",
                "code": "ZM"
            },
            "ZW": {
                "name": "Zimbabwe",
                "dial_code": "+263",
                "code": "ZW"
            }
        }

        function getCountryCode(country) {
            const countryCode = country.toUpperCase();

            // Check if the country code exists in the countryCodes object
            if (countryCodes.hasOwnProperty(countryCode)) {
                // Return the country name associated with the country code
                return countryCodes[countryCode].name;
            } else {
                return 'Nigeria';
            }
        }

		$("form.woocommerce-checkout").on('checkout_place_order', function(e) {
			// Check both radio buttons and hidden inputs
			var isShipbubbleSelected =
				$('input[name="shipping_method[0]"][value="shipbubble_shipping_services"]').is(':checked') || // Radio button
				$('input[name="shipping_method[0]"][value="shipbubble_shipping_services"][type="hidden"]').length > 0; // Hidden input

			if (isShipbubbleSelected) {
				// Skip the confirmation prompt if shipbubble is selected
				return true;
			} else {
				// Show the confirmation prompt if any other shipping method is selected
				if (!confirm("You've chosen pickup. For home delivery options, click 'Get Delivery Prices'. To proceed with pickup, click 'OK'.")) {
					console.log("Submission Stopped");
					return false;
				}
			}
		});

	});

})(jQuery);
