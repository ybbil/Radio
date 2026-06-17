const express = require("express");
const cors = require("cors");
const path = require("path");

const app = express();

app.use(cors());

app.use("/hls", express.static(path.join(__dirname, "hls"), {
  etag: false,
  lastModified: false,
  setHeaders: (res, filePath) => {
    if (filePath.endsWith(".m3u8")) {
      res.setHeader("Content-Type", "application/vnd.apple.mpegurl");
      res.setHeader("Cache-Control", "no-store, no-cache, must-revalidate, proxy-revalidate");
    }

    if (filePath.endsWith(".ts")) {
      res.setHeader("Content-Type", "video/mp2t");
      res.setHeader("Cache-Control", "public, max-age=10");
    }

    res.setHeader("Access-Control-Allow-Origin", "*");
  }
}));

app.listen(3000, "0.0.0.0", () => {
  console.log("http://0.0.0.0:3000/hls/live.m3u8");
});