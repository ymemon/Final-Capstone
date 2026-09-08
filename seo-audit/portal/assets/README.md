# Portal assets

## earth-day.b64 / earth-night.b64

Equirectangular Earth textures for the rotating globe on the Live view,
stored as ready-to-use `data:` URIs and injected into the page at build time
by `build_portal.py` (tokens `__EARTH_DAY__` / `__EARTH_NIGHT__`).

**Why data URIs rather than sibling `.jpg` files:** WebGL refuses to upload a
texture from a cross-origin image, and a page opened from disk
(`dist/<client>/index.html`, which is how these get previewed before deploy)
treats its own sibling files as cross-origin. Sibling JPEGs would therefore
render a blank globe locally and a correct one only once hosted. A `data:` URI
is same-origin everywhere, and keeps the "one self-contained file, deploy is a
straight scp" property the rest of the portal relies on.

**Source:** NASA Visible Earth, Blue Marble — public domain.
- day: `land_shallow_topo_2048.jpg`, resized to 1024x512, slight saturation and
  contrast lift (the raw composite is deliberately flat and reads washed out
  once the lighting term dims it). JPEG q78.
- night: `earth_lights_lrg.jpg`, resized to 768x384. **Not** used as-is: that
  image carries a dark-blue basemap under the city lights, which rendered as a
  purple haze across the whole night side. The lights are isolated by taking
  `(R+G)/2` (the basemap lives almost entirely in blue), flooring at 7/255,
  scaling 2.7x and tinting sodium-warm, which leaves true black ocean and
  clean pinpoint cities. JPEG q62.

Regenerate with `scripts/make_earth_textures.py` if the source is ever updated.
Total added page weight: ~156 KB.
