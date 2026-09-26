import zipfile, os, sys
def build(src, zipname, slug):
    out = f"dist/{zipname}.zip"
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
        for root, _, files in os.walk(src):
            for f in files:
                p = os.path.join(root, f)
                z.write(p, (slug + "/" + os.path.relpath(p, src)).replace("\\", "/"))
    print(out, os.path.getsize(out))
build("wp-content/plugins/aflanex-community", "aflanex-community-plugin", "aflanex-community")
build("wp-content/themes/aflanex-community", "aflanex-community-theme", "aflanex-community")
