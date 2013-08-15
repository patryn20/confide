{{ "\n\n" }}
@if (! $restful)

// Confide routes
Route::get( '{{ lcfirst(substr($name,0,-10)) }}/create',                 '{{ $controller_prefix }}{{ $name }}@create');
Route::post('{{ lcfirst(substr($name,0,-10)) }}',                        '{{ $controller_prefix }}{{ $name }}@store');
Route::get( '{{ lcfirst(substr($name,0,-10)) }}/login',                  '{{ $controller_prefix }}{{ $name }}@login');
Route::post('{{ lcfirst(substr($name,0,-10)) }}/login',                  '{{ $controller_prefix }}{{ $name }}@do_login');
Route::get( '{{ lcfirst(substr($name,0,-10)) }}/confirm/{code}',         '{{ $controller_prefix }}{{ $name }}@confirm');
Route::get( '{{ lcfirst(substr($name,0,-10)) }}/forgot_password',        '{{ $controller_prefix }}{{ $name }}@forgot_password');
Route::post('{{ lcfirst(substr($name,0,-10)) }}/forgot_password',        '{{ $controller_prefix }}{{ $name }}@do_forgot_password');
Route::get( '{{ lcfirst(substr($name,0,-10)) }}/reset_password/{token}', '{{ $controller_prefix }}{{ $name }}@reset_password');
Route::post('{{ lcfirst(substr($name,0,-10)) }}/reset_password',         '{{ $controller_prefix }}{{ $name }}@do_reset_password');
Route::get( '{{ lcfirst(substr($name,0,-10)) }}/logout',                 '{{ $controller_prefix }}{{ $name }}@logout');
@else

// Confide RESTful route
Route::get('user/confirm/{code}', '{{ $controller_prefix }}{{ $name }}@getConfirm');
Route::get('user/reset/{token}', '{{ $controller_prefix }}{{ $name }}@getReset');
Route::controller( 'user', '{{ $controller_prefix }}{{ $name }}');
@endif
