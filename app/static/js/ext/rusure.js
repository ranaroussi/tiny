


(function (rusure, undefined) {
    rusure.show = function (msg, { title, question, button, warning, onConfirm, onCancel, doubleConfirmation, hideOnConfirm = true, params = [] } = {}) {
        msg = msg || 'Are you sure you want to proceed?';
        if (!onConfirm) {
            onConfirm = () => { };
        }
        if (!onCancel) {
            onCancel = () => { };
        }
        onDocReady(() => {
            // eslint-disable-next-line no-underscore-dangle
            const stack = getStack('rusure-dialog');
            stack.title = title || 'Are you sure?';
            stack.msg = msg;
            stack.question = question || 'Are you sure you want to proceed?';
            stack.button = button || 'Yes, please proceed';
            stack.warning = typeof warning == 'undefined' ? true : warning,
                stack.params = params;
            stack.confirmCallback = onConfirm;
            stack.cancelCallback = onCancel;
            stack.doubleConfirmation = doubleConfirmation || false;
            stack.hideOnConfirm = hideOnConfirm;
            stack.show = true;
            document.getElementById('rusure-dialog').focus();
        });
    };

    rusure.cancel = function () {
        onDocReady(() => {
            // eslint-disable-next-line no-underscore-dangle
            const stack = getStack('rusure-dialog');
            stack.cancel();
        });
    };

}(window.rusure = window.rusure || {}));


function rusureDialog() {
    return {
        show: false,
        title: '',
        msg: '',
        question: '',
        button: 'Yes, please proceed',
        warning: true,
        doubleConfirmation: false,
        // transitionClass: 'fade-in',
        confirmCallback: () => { },
        cancelCallback: () => { },
        clicks: 0,
        params: [],

        init() {
            // console.log('init');
            // this.confirm();
        },

        rusureConfirm() {
            this.clicks++;
            const button = document.getElementById('rusure-button');
            // if (this.clicks == 1) {
            //   this.button = button.innerHTML;
            // }
            if (this.doubleConfirmation) {
                button.style.width = button.offsetWidth + 'px';
                button.classList.add('scale-95');
                button.classList.add('opacity-75');
                setTimeout(() => {
                    button.classList.remove('scale-95');
                    button.classList.remove('opacity-75');
                }, 200);
                button.innerHTML = 'Click again to confirm...';
                this.doubleConfirmation = false;
                return;
            }
            button.innerHTML = 'Executing...'; // this.button
            showLoading(button);
            this.confirmCallback(...this.params);
            if (this.hideOnConfirm) {
                hideLoading(button);
                this.hide();
            }
        },

        hide() {
            this.show = false;
            setTimeout(() => {
                this.title = 'Are you sure?';
                this.msg = '';
                this.button = 'Yes, please proceed';
                this.params = [];
                this.confirmCallback = () => { };
                this.cancelCallback = () => { };
            }, 750);
        },

        rusureCancel() {
            this.cancelCallback(...this.params);
            this.hide();
        }
    };
}
