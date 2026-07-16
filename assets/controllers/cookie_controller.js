import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['banner', 'bannerWrapper', 'modalCookie', 'consentForm', 'manageButton'];

    connect() {
        // Initialize preferences without setting any cookies on the first page load
        this.cookieScopes = this.getCookiePreferences() || {
            rememberMe: false,
            geolocation: false,
        };

        this.updateCheckboxes();
        this.checkCookies();
        this.toggleManageButton();

        if (this.cookieScopes.geolocation) {
            this.askForLocation();
        }
    }

    checkCookies() {
        const hasAcceptedCookies = this.getCookie('cookies_accepted');
        const hasSavedPreferences = this.getCookie('cookie_preferences');

        // If either cookies_accepted or cookie_preferences exists and cookies were not rejected, hide the banner
        if (hasAcceptedCookies || hasSavedPreferences) {
            this.hideBanner();
        }
    }

    showBanner() {
        this.bannerTarget.classList.remove('hidden');
        this.toggleManageButton();
    }

    hideBanner() {
        if (this.hasBannerWrapperTarget) {
            this.bannerWrapperTarget.remove();
        }
        this.toggleManageButton();
    }

    toggleManageButton() {
        if (!this.hasBannerWrapperTarget) {
            this.manageButtonTarget.classList.remove('hidden');
            return;
        }

        this.manageButtonTarget.classList.add('hidden');
    }

    showModal() {
        this.modalCookieTarget.classList.remove('hidden');
    }

    closeModal() {
        this.modalCookieTarget.classList.add('hidden');
    }

    acceptCookies() {
        Object.keys(this.cookieScopes).forEach((scope) => {
            this.cookieScopes[scope] = true;
            this.updateCheckbox(scope, true);
        });

        this.setCookiePreferences();
        this.setCookiesAccepted();

        this.handleGeolocationConsent();

        this.hideBanner();
    }

    rejectCookies() {
        this.clearAllCookies();
        this.closeModal();
        this.hideBanner();
    }

    savePreferences() {
        this.consentFormTarget.querySelectorAll('[data-scope]').forEach((checkbox) => {
            const scope = checkbox.getAttribute('data-scope');
            this.cookieScopes[scope] = checkbox.checked;
        });

        this.setCookiePreferences();

        this.handleGeolocationConsent();

        const allEnabled = Object.values(this.cookieScopes).every((val) => val === true);
        if (allEnabled) {
            this.setCookiesAccepted();
        } else {
            document.cookie = 'cookies_accepted=; path=/; max-age=0; Secure; SameSite=Lax';
        }

        this.closeModal();
        this.hideBanner();
    }

    handleGeolocationConsent() {
        if (this.cookieScopes.geolocation) {
            this.askForLocation();
        } else {
            document.cookie = 'user_lat=; path=/; max-age=0; Secure; SameSite=Strict';
            document.cookie = 'user_lng=; path=/; max-age=0; Secure; SameSite=Strict';
        }
    }

    askForLocation() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const maxAge = 365 * 24 * 60 * 60;

                    document.cookie = `user_lat=${lat}; path=/; max-age=${maxAge}; Secure; SameSite=Strict`;
                    document.cookie = `user_lng=${lng}; path=/; max-age=${maxAge}; Secure; SameSite=Strict`;
                },
                (error) => {
                    console.error('Error:', error);
                }
            );
        }
    }

    updateCheckbox(scope, checked) {
        const checkbox = this.consentFormTarget.querySelector(`[data-scope="${scope}"]`);
        if (checkbox) checkbox.checked = checked;
    }

    updateCheckboxes() {
        Object.entries(this.cookieScopes).forEach(([scope, checked]) =>
            this.updateCheckbox(scope, checked)
        );
    }

    setCookiePreferences() {
        document.cookie =
            'cookie_preferences=' +
            JSON.stringify(this.cookieScopes) +
            '; path=/; max-age=' +
            365 * 24 * 60 * 60 +
            '; Secure; SameSite=Lax';
    }

    setCookiesAccepted() {
        document.cookie = 'cookies_accepted=true; path=/; max-age=' + 365 * 24 * 60 * 60 + '; Secure; SameSite=Lax';
    }

    getCookiePreferences() {
        const cookie = this.getCookie('cookie_preferences');
        return cookie ? JSON.parse(cookie) : null;
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    clearAllCookies() {
        const cookies = document.cookie.split(';');
        cookies.forEach((cookie) => {
            const eqPos = cookie.indexOf('=');
            const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
            document.cookie = name.trim() + '=; path=/; max-age=0; Secure; SameSite=Lax';
        });

        localStorage.clear();
    }
}