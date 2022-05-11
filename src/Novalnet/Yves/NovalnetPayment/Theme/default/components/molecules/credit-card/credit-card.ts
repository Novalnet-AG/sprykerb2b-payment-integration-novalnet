import Component from 'ShopUi/models/component';

const CURRENT_PAYMENT_METHOD = 'novalnetCreditCard';

export default class CreditCard extends Component {
    form: HTMLFormElement;

    protected button: HTMLButtonElement[];

    protected readyCallback(): void {}
    
    protected formObj() { return this; }

    protected init(): void {
        document.getElementById("paymentForm_novalnetCreditCard_panHash").value = '';
        this.initiateIframe();

        this.form = document.querySelector(this.formSelector);
        var buttonList = this.form.getElementsByTagName("button");
        this.button = buttonList[0];

        var rad = document.getElementsByName('paymentForm[paymentSelection]');
        for (var i = 0; i < rad.length; i++) {
            if (rad[i] && rad[i].type == 'radio') {
                rad[i].addEventListener('change', function() {
                    var buttonList = this.form.getElementsByTagName("button");
                    this.button = buttonList[0];
                    if (this.value != CURRENT_PAYMENT_METHOD) {
                        this.button.classList.remove("nn_cc_submit");
                    } else {
                        this.button.classList.add("nn_cc_submit");
                    }
                });
            }
        }
        
        var objRef = this.formObj();
        
		document.querySelectorAll("#payment-form input[name='paymentForm[paymentSelection]']").forEach(function (payment, i) {
			payment.addEventListener("click", function() {
				if (payment.value === CURRENT_PAYMENT_METHOD) {
					document.querySelector("toggler-radio#" + payment.id).setAttribute("checked", "checked");
					document.querySelector("input[type=radio]#" + payment.id).setAttribute("checked", "checked");
					if (objRef.isCurrentPaymentMethod) {
						objRef.mapEvents();
					}
				}
			});
		});

        this.mapEvents();
    }

    protected mapEvents(): void {
        if (this.isCurrentPaymentMethod) {
            this.setupPanhashCall();
        }
    }

    protected setupPanhashCall(): void {
        this.button.classList.add("nn_cc_submit");
        var parentDiv = this.button.parentNode;
        parentDiv.addEventListener('click', event => this.onSubmit(event));
    }

    protected onSubmit(event: Event): void {
        if (!this.isCurrentPaymentMethod || event.target.classList.contains('button--back')) {
          return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (this.isCurrentPaymentMethod) {
            window.scrollTo(0, 200);
            NovalnetUtility.getPanHash();
        }
    }

    get formSelector(): string {
        return this.getAttribute('form-selector');
    }

    get currentPaymentMethodSelector(): string {
        return this.getAttribute('current-payment-method-selector');
    }

    get isCurrentPaymentMethod(): boolean | null {
        const currentPaymentMethodInput = <HTMLInputElement>document.querySelector(this.currentPaymentMethodSelector);

        return currentPaymentMethodInput?.value
            ? currentPaymentMethodInput.value === CURRENT_PAYMENT_METHOD
            : null;
    }

    protected initiateIframe() {
        var iframe = document.getElementById('novalnet_cc_iframe').contentWindow;
        if (document.getElementById("paymentForm_novalnetCreditCard_formClientKey").value === undefined ||
            document.getElementById("paymentForm_novalnetCreditCard_formClientKey").value == '') {
            return false;
        }
        NovalnetUtility.setClientKey(document.getElementById("paymentForm_novalnetCreditCard_formClientKey").value);
        var request_object = {
            callback: {
                on_success: function(data) {
                    document.getElementById("paymentForm_novalnetCreditCard_panHash").value = data['hash'];
                    document.getElementById("paymentForm_novalnetCreditCard_uniqueId").value = data['unique_id'];
                    document.getElementById("paymentForm_novalnetCreditCard_doRedirect").value = data['do_redirect'];
                    document.getElementsByClassName('nn_cc_submit')[0].setAttribute('disabled', 'disabled');
                    document.paymentForm.submit();
                },
                on_error: function on_error(data) {
                    alert(data['error_message']);
                    document.getElementById("nn_overlay").classList.remove("novalnet-challenge-window-overlay");
                    document.getElementById("novalnet_cc_iframe").classList.remove("novalnet-challenge-window-overlay");
                },
                on_show_overlay: function on_show_overlay() {
                    document.getElementById('novalnet_cc_iframe').classList.add("novalnet-challenge-window-overlay");
                },
                on_hide_overlay: function on_hide_overlay() {
                    document.getElementById("novalnet_cc_iframe").classList.remove("novalnet-challenge-window-overlay");
                    document.getElementById("nn_overlay").classList.add("novalnet-challenge-window-overlay");
                },
                on_show_captcha: function on_show_captcha() {
                    window.scrollTo(0, 200);
                }
            },
            iframe: {
                id: 'novalnet_cc_iframe',
                inline: document.getElementById("paymentForm_novalnetCreditCard_formInline").value,
                style: {
                    container: document.getElementById("paymentForm_novalnetCreditCard_formStyleContainer").value,
                    input: document.getElementById("paymentForm_novalnetCreditCard_formStyleInput").value,
                    label: document.getElementById("paymentForm_novalnetCreditCard_formStyleLabel").value,
                }
            },
            customer: {
                first_name: document.getElementById("paymentForm_novalnetCreditCard_firstName").value,
                last_name: document.getElementById("paymentForm_novalnetCreditCard_lastName").value,
                email: document.getElementById("paymentForm_novalnetCreditCard_email").value,
                billing: {
                    street: document.getElementById("paymentForm_novalnetCreditCard_street").value + ',' + document.getElementById("paymentForm_novalnetCreditCard_houseNo").value,
                    city: document.getElementById("paymentForm_novalnetCreditCard_city").value,
                    zip: document.getElementById("paymentForm_novalnetCreditCard_zip").value,
                    country_code: document.getElementById("paymentForm_novalnetCreditCard_countryCode").value,
                },
            },
            transaction: {
                amount: document.getElementById("paymentForm_novalnetCreditCard_amount").value,
                currency: document.getElementById("paymentForm_novalnetCreditCard_currency").value,
                test_mode: document.getElementById("paymentForm_novalnetCreditCard_testMode").value,
            },
            custom: {
                lang: document.getElementById("paymentForm_novalnetCreditCard_lang").value
            }
        };
        NovalnetUtility.createCreditCardForm(request_object);
    }
}
