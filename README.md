# xrns2mod

> A tool to convert XRNS (Renoise) songs to MOD file format.

Is that a good idea? Will you get accurate results? Quite probably not, but it can be an interesting exercise on file formats and handling.

**Note:** *I haven't ran this in approximately 10 years; I found it while sorting old stuff, and this probably won't work as I think Renoise has changed file formats too (I might be wrong though).*

**ALSO**: *there seems to be a working project that does it all and does it from within Renoise: [XRNS2XMOD](https://xrns2xmod.codeplex.com/) - converts both to MOD and XM. I haven't ran it myself but it looks promising!*

Shared for educational purposes. No support provided.

[![No Maintenance Intended](http://unmaintained.tech/badge.svg)](http://unmaintained.tech/)

If I was to write this nowadays I would probably build it with Node.js because it's what I'm most familiar with nowadays, and I would also split it in files: one for each class, one for the main interface to the utility, and one for the command line executable file. It would also be neat to look into getting sox working on the browser via Emscripten or something like that so we could convert songs in the browser! (you can consider this your homework if you're so naturally inclined 🙃)

## "Documentation"

I wrote this before GitHub even existed and there's no documentation for it yet, or ever, other than reading the code.

It looks like both the input and output file names are hardcoded at the beginning of the *one and only* file (`xrns2mod.php`).

It also requires that you have the [sox](http://sox.sourceforge.net/) utility installed in your system as it needs to convert Renoise's high quality stereo FLAC samples into the type of mono 8000 Hz samples the MOD format uses. Sox utility is called directly, with `shell_exec`; no wrapper libraries are used.

Not all the effects are supported and probably not all the sample configuration parameters etc. From what I remember there were also differences on the range of notes Renoise can play versus what MOD can. There might be other quirks. Modules are a funny file format.

## Contributing

I don't have the time to work on this project but if you want to adopt it, get in touch!

## Credits

It seems that I used the source code of ModPlug for understanding the quirks of the MOD format. Thanks ModPlug!

I wrote the `test.xrns` and `test2.xrns` 'song' files. Although they are not the peak of my composing abilities please respect my intellectual ownership and credit me if you use them. I don't quite remember what was I trying to test with each one - I think the first was only testing for exporting the pattern data, using bad quality samples, while the second would test converting big samples.

