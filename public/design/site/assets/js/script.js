const tabs=document.querySelectorAll(".tab-btn");

const contents=document.querySelectorAll(".tab-content");

tabs.forEach(tab=>{

tab.onclick=function(){

tabs.forEach(btn=>btn.classList.remove("active"));

contents.forEach(c=>c.classList.remove("active"));

this.classList.add("active");

document.getElementById(this.dataset.tab).classList.add("active");

}

});