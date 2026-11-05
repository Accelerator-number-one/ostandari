
function mainslider() {
    jQuery('#owl-slider').owlCarousel({
        items: 1,
        nav: true,
        dots: false,
        autoplay: true,
        loop: true,
        mouseDrag: true,
        touchDrag: true,
        animateIn: 'fadeIn', // add this
        animateOut: 'fadeOut', // and this
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 4000,
    });
}


function shopslider() {
    jQuery('#owl-shopslider').owlCarousel({
        items: 1,
        nav: true,
        dots: true,
        autoplay: true,
        loop: true,
        mouseDrag: true,
        touchDrag: true,
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 4000,
    });
}


function owlgallery() {
    jQuery('#owl-gallery').owlCarousel({
        items: 1,
        nav: true,
        dots: false,
        autoplay: true,
        loop: true,
        mouseDrag: true,
        touchDrag: true,
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 4000,
    });
}

//event
function owlevent1() {
    jQuery('.owl-event1').owlCarousel({
        rtl: true,
        loop: true,
        nav: true,
        autoplay: true,
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 8000,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1,
            },
            410: {
                items: 1,
            },
            577: {
                items: 2,
            },
            769: {
                items: 2,
            },
            1200: {
                items: 3,
            },
            1400: {
                items: 4,
            }
        }
    });
}



//event
function owllink() {
    jQuery('.owl-link').owlCarousel({
        rtl: true,
        loop: true,
        nav: true,
        autoplay: true,
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 8000,
        responsiveClass: true,
        responsive: {
            0: {
                items:2,
            },
            410: {
                items: 2,
            },
            577: {
                items: 3,
            },
            769: {
                items: 3,
            },
            1200: {
                items: 4,
            },
            1400: {
                items: 5,
            }
        }
    });
}





















function owlevent222() {
    jQuery('.test27').owlCarousel({
        rtl: true,
        loop: true,
        nav: true,
        autoplay: true,
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 8000,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1,
            },
            410: {
                items: 2,
            },
            577: {
                items: 3,
            },
            769: {
                items: 3,
            },
            1200: {
                items: 4,
            },
            1400: {
                items: 5,
            }
        }
    });
}



//event
function owlsafiran() {
    jQuery('.owl-safiran').owlCarousel({
        rtl: true,
        loop: true,
        nav: true,
        autoplay: true,
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 8000,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1,
            },
            400: {
                items: 2,
            },
            576: {
                items: 2,
            },
            769: {
                items: 3,
            },
            1200: {
                items: 4,
            },
            1400: {
                items: 6,
            }
        }
    });
}



//event
function owlproduct() {
    jQuery('.owl-product').owlCarousel({
        rtl: true,
        loop: true,
        nav: true,
        autoplay: true,
        autoplayHoverPause: true,
        smartSpeed: 1500,
        autoplayTimeout: 8000,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1,
            },
            481: {
                items: 2,
            },
            577: {
                items: 2,
            },
            769: {
                items: 3,
            },
            1200: {
                items: 4,
            }
        }
    });
}


// teletype
function teletype() {
    jQuery('.animated-text').typed({
        strings: [

            " قالب چند منظوره شمیم :)  ",
            " سامانه جامع برترین کارشناسان  ",
            " تسهیل در خدمات ..  ",
            " طراحی انواع وب سایت ..  ",
        ],
        typeSpeed: 130,
        loop: true,
    });
}





/**** All function are called here ******/
$(document).ready(function () {

    mainslider(),
    shopslider(),
    owlevent1(),
    owllink(),
    owlsafiran(),
    owlproduct(),
    teletype(),
    owlgallery();
});


//popupgallery
$('.popup-gallery').magnificPopup({
    delegate: 'a',
    type: 'image',
    tLoading: 'Loading image #%curr%...',
    mainClass: 'mfp-with-zoom mfp-img-mobile',
    gallery: {
        enabled: true,
        navigateByImgClick: true,
        preload: [0, 1]
    },
    zoom: {
        enabled: true,
        duration: 300
    },
    image: {
        tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
    }
});



/*----------search full------------------------*/
function openSearch() {
    document.getElementById("myOverlay").style.display = "block";
}

function closeSearch() {
    document.getElementById("myOverlay").style.display = "none";
}



// Increase/descrease font size
$('#increasetext').click(function () {
    curSize = parseInt($('#content').css('font-size')) + 1;
    if (curSize <= 22)
        $('#content').css('font-size', curSize);
});


$('#decreasetext').click(function () {
    curSize = parseInt($('#content').css('font-size')) - 1;
    if (curSize >= 13)
        $('#content').css('font-size', curSize);
});


$(document).ready(function ($) {
    $('#Gallery_Product').sliderPro({
        width: 800,
        height: 850,
        fade: false,
        arrows: true,
        buttons: false,
        fullScreen: false,
        shuffle: false,
        smallSize: 500,
        mediumSize: 1000,
        largeSize: 3000,
        thumbnailArrows: true,
        autoplay: false
    });
});


$(document).ready(function ($) {
    $('#Gallery_Safir').sliderPro({
        width: 800,
        height: 950,
        fade: false,
        arrows: true,
        buttons: false,
        fullScreen: false,
        shuffle: false,
        smallSize: 500,
        mediumSize: 1000,
        largeSize: 3000,
        thumbnailArrows: true,
        autoplay: false
    });
});


