const bundleUrl="https://hotelvastu.com/assets/index-IDoo_XgH.js";
const res=await fetch(bundleUrl,{headers:{"user-agent":"Mozilla/5.0 HotelVastuMigration/3.0"}});
const text=await res.text();
console.log("BUNDLE",bundleUrl,"STATUS",res.status,"LENGTH",text.length);

const needles=[
  "classic-room",
  "Classic Room",
  "classic room",
  "index-4/room/1.webp",
  "index-4/room/2.webp",
  "index-4/room/3.webp",
  "bookingapi.hotelvastu.com/api/",
  "add-reservation-data",
  "room-detail"
];

for(const needle of needles){
  console.log("\n=== NEEDLE",needle,"===");
  const lower=text.toLowerCase();
  const target=needle.toLowerCase();
  let start=0;
  let count=0;
  while(true){
    const i=lower.indexOf(target,start);
    if(i<0) break;
    count++;
    const from=Math.max(0,i-900);
    const to=Math.min(text.length,i+1800);
    console.log("\nMATCH",count,"AT",i);
    console.log(text.slice(from,to).replace(/\s+/g," "));
    start=i+needle.length;
    if(count>=8) break;
  }
  if(!count) console.log("NO_MATCH");
}
