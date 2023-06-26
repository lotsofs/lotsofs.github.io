// code standing inside nav.js for easy edit
const nav = `
    <a href="/" class="here"><img src="image.png" alt="Home"/></a>
    <a href="/about.html" >About</a>      
    <a href="/services.html" >Services</a>          
    <a href="/pricing.html" >Pricing</a>    
    <a href="/contact.html" >Contact Us</a>
`;

window.onload = function() {
    document.getElementById("Abcd").innerHTML = nav;
};

// window.addEventListener('DOMContentLoaded', () => {
//     let barnav = document.querySelector('nav[role="navigation"]');
//     barnav.innerHTML = nav;
//     barnav.getElementsByTagName
//  });