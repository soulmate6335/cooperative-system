import type { ReactNode } from 'react'
import { Controller, useFormContext, type FieldValues, type Path } from 'react-hook-form'
import TextField from '@mui/material/TextField'
import type { TextFieldProps } from '@mui/material/TextField'

type FormTextFieldProps<T extends FieldValues> = Omit<TextFieldProps, 'name' | 'value' | 'onChange' | 'onBlur' | 'ref'> & {
  name: Path<T>
  label: string
}

export function FormTextField<T extends FieldValues>({ name, label, ...rest }: FormTextFieldProps<T>): ReactNode {
  const { control } = useFormContext<T>()

  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <TextField
          {...rest}
          id={name}
          label={label}
          name={field.name}
          value={field.value ?? ''}
          onChange={field.onChange}
          onBlur={field.onBlur}
          inputRef={field.ref}
          error={Boolean(fieldState.error)}
          helperText={fieldState.error ? fieldState.error.message : rest.helperText}
        />
      )}
    />
  )
}