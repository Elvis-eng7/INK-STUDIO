document.addEventListener("DOMContentLoaded", function () {

    // ==========================================
    // 1. BOOKING FORM ELEMENTS
    // ==========================================

    const bookingForm = document.getElementById("bookingForm");
    const tattooType = document.getElementById("tattooType");
    const tattooSize = document.getElementById("tattooSize");
    const priceDisplay = document.getElementById("priceDisplay");
    const emailInput = document.getElementById("email");
    const appointmentDate = document.getElementById("appointmentDate");


    // ==========================================
    // 2. TATTOO PRICES IN USD
    // ==========================================

    const prices = {
        "fine-line": {
            small: 50,
            medium: 100,
            large: 180
        },

        realism: {
            small: 100,
            medium: 200,
            large: 350
        },

        custom: {
            small: 80,
            medium: 150,
            large: 250
        }
    };


    // ==========================================
    // 3. AUTOMATIC PRICE CALCULATOR
    // ==========================================

    function calculatePrice() {

        if (!tattooType || !tattooSize || !priceDisplay) {
            return;
        }

        const type = tattooType.value;
        const size = tattooSize.value;

        if (!type || !size) {
            priceDisplay.textContent =
                "Estimated Price: Select type and size";

            return;
        }

        if (!prices[type] || prices[type][size] === undefined) {
            priceDisplay.textContent =
                "Estimated Price: Unable to calculate";

            return;
        }

        const price = prices[type][size];

        priceDisplay.textContent =
            `Estimated Price: $${price.toFixed(2)} USD`;
    }


    if (tattooType) {
        tattooType.addEventListener("change", calculatePrice);
    }

    if (tattooSize) {
        tattooSize.addEventListener("change", calculatePrice);
    }

    calculatePrice();


    // ==========================================
    // 4. GMAIL VALIDATION
    // ==========================================

    if (emailInput) {

        emailInput.addEventListener("input", function () {

            emailInput.setCustomValidity("");

            const email = emailInput.value.trim();

            if (
                email !== "" &&
                !/^[a-zA-Z0-9._%+-]+@gmail\.com$/i.test(email)
            ) {

                emailInput.setCustomValidity(
                    "Please enter a valid Gmail address ending in @gmail.com."
                );

            }

        });

    }


    // ==========================================
    // 5. BOOKING FORM VALIDATION
    // ==========================================

    if (bookingForm) {

        bookingForm.addEventListener("submit", function (event) {

            if (!bookingForm.checkValidity()) {

                event.preventDefault();
                bookingForm.reportValidity();

            }

        });

    }


    // ==========================================
    // 6. APPOINTMENT DATE VALIDATION
    // ==========================================

    if (appointmentDate) {

        const today = new Date();

        const year = today.getFullYear();

        const month = String(today.getMonth() + 1).padStart(2, "0");

        const day = String(today.getDate()).padStart(2, "0");

        const todayDate = `${year}-${month}-${day}`;

        appointmentDate.min = todayDate;

    }


    // ==========================================
    // 7. TATTOO IMAGE CAROUSEL
    // ==========================================

    const tattooImages = [
        "imagessrcs/WhatsApp%20Image%202026-10-24%20at%2018.24.46.jpeg",
        "imagessrcs/WhatsApp%20Image%202026-10-24%20at%2018.24.45.jpeg",
        "imagessrcs/firtstimage.jpeg"
    ];

    let currentImage = 0;

    const mainSlide = document.querySelector(".main-slide");
    const backOne = document.querySelector(".back-one");
    const backTwo = document.querySelector(".back-two");

    const imageIndicator =
        document.getElementById("imageIndicator");

    const nextImage =
        document.getElementById("nextImage");


    function updateCarousel() {

        if (!mainSlide || !backOne || !backTwo) {
            return;
        }

        mainSlide.src = tattooImages[currentImage];

        backOne.src =
            tattooImages[(currentImage + 1) % tattooImages.length];

        backTwo.src =
            tattooImages[(currentImage + 2) % tattooImages.length];

        if (imageIndicator) {

            imageIndicator.textContent =
                `${currentImage + 1} / ${tattooImages.length}`;

        }

    }


    if (nextImage) {

        nextImage.addEventListener("click", function () {

            currentImage =
                (currentImage + 1) % tattooImages.length;

            updateCarousel();

        });

    }


    // ==========================================
    // 8. INITIALISE CAROUSEL
    // ==========================================

    updateCarousel();

});