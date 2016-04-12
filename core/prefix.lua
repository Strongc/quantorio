old_require = require
require = function (path)
	return php_findfile(path)
end
-- function module() end

game = {}
defines = {}
util = {}
util.table = {}
