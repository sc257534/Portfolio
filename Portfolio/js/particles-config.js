particlesJS('particles-js', {
    particles: {
        number: {
            value: window.innerWidth < 600 ? 40 : 80, // Fewer particles on mobile
            density: {
                enable: true,
                value_area: 800
            }
        },
        color: {
            value: "#000000" // Black particles
        },
        shape: {
            type: "circle",
            stroke: {
                width: 0,
                color: "#000000"
            },
            polygon: {
                nb_sides: 5
            }
        },
        opacity: {
            value: 0.9, // More visible
            random: false,
            anim: {
                enable: false,
                speed: 1,
                opacity_min: 1,
                sync: false
            }
        },
        size: {
            value: window.innerWidth < 600 ? 4 : 3, // Slightly larger on mobile
            random: true,
            anim: {
                enable: false,
                speed: 40,
                size_min: 0.1,
                sync: false
            }
        },
        line_linked: {
            enable: true,
            distance: 150,
            color: "#000000", // Black lines
            opacity: 0.7, // More visible
            width: 1.2
        },
        move: {
            enable: true,
            speed: 4, // Slightly slower for mobile smoothness
            direction: "none",
            random: false,
            straight: false,
            out_mode: "out",
            bounce: false,
            attract: {
                enable: false,
                rotateX: 600,
                rotateY: 1200
            }
        }
    },
    interactivity: {
        detect_on: "canvas",
        events: {
            onhover: {
                enable: true,
                mode: "grab"
            },
            onclick: {
                enable: true,
                mode: "push"
            },
            resize: true
        },
        modes: {
            grab: {
                distance: 400,
                line_linked: {
                    opacity: 1
                }
            },
            bubble: {
                distance: 400,
                size: 40,
                duration: 2,
                opacity: 8,
                speed: 3
            },
            repulse: {
                distance: 200,
                duration: 0.4
            },
            push: {
                particles_nb: 4
            },
            remove: {
                particles_nb: 2
            }
        }
    },
    retina_detect: true
});